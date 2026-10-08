<?php

namespace App\Services\Calendar;

use App\Contracts\CalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outlook-agenda's via Microsoft Graph, met één koppeling voor heel GKR (ADR-011).
 *
 * - Authenticatie: client credentials (applicatierechten Calendars.ReadWrite en
 *   MailboxSettings.ReadWrite), in Exchange ingeperkt tot de groep "Klantportaal-agendas".
 * - Elke aanroep heeft een expliciete timeout. Tijdelijke fouten (timeout, 429, 5xx) worden
 *   CalendarTemporarilyUnavailable, blijvende fouten CalendarRejected. Graph-foutcodes en
 *   -berichten komen nooit buiten deze klasse.
 * - Logging bevat alleen de operatie, HTTP-status en Graph-foutcode: geen e-mailadressen,
 *   onderwerpen of andere persoonsgegevens.
 */
class GraphCalendarProvider implements CalendarProvider
{
    private const TOKEN_CACHE_KEY = 'calendar.graph.token';

    /** @var array<string, array<string, array{id: string, color: string}>> */
    private array $masterCategories = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds,
        private readonly string $timezone,
    ) {}

    public function upsertMeeting(CalendarMeeting $meeting): CalendarMeetingResult
    {
        $payload = [
            'subject' => $meeting->subject,
            'body' => ['contentType' => 'text', 'content' => $meeting->body],
            'start' => $this->dateTime($meeting->start),
            'end' => $this->dateTime($meeting->end),
            'location' => ['displayName' => $meeting->location],
            'attendees' => [
                ...array_map(fn (CalendarAttendee $a) => $this->attendee($a, 'required'), $meeting->requiredAttendees),
                ...array_map(fn (CalendarAttendee $a) => $this->attendee($a, 'optional'), $meeting->optionalAttendees),
            ],
            'allowNewTimeProposals' => false,
            'isOnlineMeeting' => $meeting->online,
        ];

        if ($meeting->online) {
            $payload['onlineMeetingProvider'] = 'teamsForBusiness';
        }

        $events = '/users/'.rawurlencode($meeting->organizerEmail).'/events';

        if ($meeting->existingId !== null) {
            $response = $this->send('afspraak bijwerken', 'PATCH', $events.'/'.rawurlencode($meeting->existingId), $payload, notFoundOk: true);

            if ($response->status() !== 404) {
                return $this->meetingResult($response);
            }
            // In Outlook met de hand verwijderd: opnieuw aanmaken.
        }

        // transactionId maakt aanmaken idempotent: een herhaalde poging na een timeout levert
        // geen tweede event op.
        $payload['transactionId'] = $meeting->idempotencyKey;

        return $this->meetingResult($this->send('afspraak aanmaken', 'POST', $events, $payload));
    }

    public function cancelMeeting(string $organizerEmail, string $externalId, ?string $comment = null): void
    {
        $this->send(
            'afspraak annuleren',
            'POST',
            '/users/'.rawurlencode($organizerEmail).'/events/'.rawurlencode($externalId).'/cancel',
            ['comment' => $comment ?? ''],
            notFoundOk: true,
        );
    }

    public function upsertBlock(CalendarBlock $block): string
    {
        $payload = [
            'subject' => $block->subject,
            'start' => $this->dateTime($block->start),
            'end' => $this->dateTime($block->end),
            'showAs' => 'busy',
            'isReminderOn' => false,
        ];

        $events = '/users/'.rawurlencode($block->mailbox).'/events';

        if ($block->existingId !== null) {
            $response = $this->send('blok bijwerken', 'PATCH', $events.'/'.rawurlencode($block->existingId), $payload, notFoundOk: true);

            if ($response->status() !== 404) {
                return (string) $response->json('id');
            }
        }

        return (string) $this->send('blok aanmaken', 'POST', $events, $payload)->json('id');
    }

    public function deleteBlock(string $mailbox, string $externalId): void
    {
        $this->send(
            'blok verwijderen',
            'DELETE',
            '/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($externalId),
            notFoundOk: true,
        );
    }

    public function busyPeriods(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [];

        foreach ($this->schedule($emails, $from, $to) as $schedule) {
            $email = strtolower((string) ($schedule['scheduleId'] ?? ''));

            if (isset($schedule['error'])) {
                $this->log('beschikbaarheid ophalen', null, (string) ($schedule['error']['responseCode'] ?? 'scheduleError'));

                throw new CalendarRejected('De Outlook-agenda van een medewerker kon niet worden gelezen.');
            }

            $result[$email] = [];

            foreach ($schedule['scheduleItems'] ?? [] as $item) {
                $kind = match (strtolower((string) ($item['status'] ?? ''))) {
                    'free', 'workingelsewhere' => null,
                    'tentative' => BusyKind::Tentative,
                    'oof' => BusyKind::Away,
                    default => BusyKind::Busy, // 'busy' en onbekende waarden: veilig als bezet
                };

                if ($kind !== null) {
                    $result[$email][] = new BusyPeriod(
                        $this->parseGraphTime($item['start']),
                        $this->parseGraphTime($item['end']),
                        $kind,
                    );
                }
            }
        }

        foreach ($emails as $email) {
            $result[strtolower($email)] ??= [];
        }

        return $result;
    }

    public function markInOverview(string $overviewMailbox, string $iCalUId, array $categories): bool
    {
        $events = '/users/'.rawurlencode($overviewMailbox).'/events';

        $found = $this->send('overzicht zoeken', 'GET', $events, query: [
            '$filter' => "iCalUId eq '".str_replace("'", "''", $iCalUId)."'",
            '$select' => 'id',
        ])->json('value', []);

        if ($found === []) {
            return false;
        }

        $eventId = rawurlencode((string) $found[0]['id']);

        // Stil accepteren: de organisator krijgt geen antwoordmail van info@.
        $this->send('overzicht accepteren', 'POST', $events.'/'.$eventId.'/accept', ['sendResponse' => false]);

        foreach ($categories as $category) {
            $this->ensureCategory($overviewMailbox, $category);
        }

        $this->send('overzicht kleuren', 'PATCH', $events.'/'.$eventId, [
            'categories' => array_map(fn (CalendarCategory $c) => $c->name, $categories),
        ]);

        return true;
    }

    public function checkMailboxes(array $emails): array
    {
        $today = CarbonImmutable::now($this->timezone)->startOfDay();
        $result = [];

        foreach ($this->schedule($emails, $today, $today->addDay()) as $schedule) {
            $email = strtolower((string) ($schedule['scheduleId'] ?? ''));
            $result[$email] = isset($schedule['error'])
                ? (string) ($schedule['error']['responseCode'] ?? 'onbekende fout')
                : null;
        }

        // array_key_exists, niet `??=`: null betekent hier "OK" en mag niet overschreven worden.
        foreach ($emails as $email) {
            if (! array_key_exists(strtolower($email), $result)) {
                $result[strtolower($email)] = 'niet teruggekregen van Outlook';
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $emails
     * @return list<array<string, mixed>>
     */
    private function schedule(array $emails, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($emails === []) {
            return [];
        }

        // Tijden in UTC opvragen én terugkrijgen: Graph geeft anders Windows-tijdzonenamen
        // ("W. Europe Standard Time") terug die PHP niet kent.
        return $this->send(
            'beschikbaarheid ophalen',
            'POST',
            '/users/'.rawurlencode($emails[0]).'/calendar/getSchedule',
            [
                'schedules' => array_values($emails),
                'startTime' => ['dateTime' => $from->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'endTime' => ['dateTime' => $to->utc()->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
                'availabilityViewInterval' => 15,
            ],
            headers: ['Prefer' => 'outlook.timezone="UTC"'],
        )->json('value', []);
    }

    private function ensureCategory(string $mailbox, CalendarCategory $category): void
    {
        $path = '/users/'.rawurlencode($mailbox).'/outlook/masterCategories';

        if (! isset($this->masterCategories[$mailbox])) {
            $this->masterCategories[$mailbox] = [];

            foreach ($this->send('kleuren ophalen', 'GET', $path)->json('value', []) as $existing) {
                $this->masterCategories[$mailbox][strtolower($existing['displayName'])] = [
                    'id' => (string) $existing['id'],
                    'color' => (string) $existing['color'],
                ];
            }
        }

        $key = strtolower($category->name);
        $existing = $this->masterCategories[$mailbox][$key] ?? null;

        if ($existing === null) {
            $created = $this->send('kleur aanmaken', 'POST', $path, [
                'displayName' => $category->name,
                'color' => $category->colorPreset,
            ]);
            $this->masterCategories[$mailbox][$key] = ['id' => (string) $created->json('id'), 'color' => $category->colorPreset];
        } elseif ($existing['color'] !== $category->colorPreset) {
            $this->send('kleur wijzigen', 'PATCH', $path.'/'.rawurlencode($existing['id']), ['color' => $category->colorPreset]);
            $this->masterCategories[$mailbox][$key]['color'] = $category->colorPreset;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    private function send(
        string $operation,
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        array $headers = [],
        bool $notFoundOk = false,
    ): Response {
        try {
            $request = $this->client()->withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($path, $query),
                'POST' => $request->post($path, $body),
                'PATCH' => $request->patch($path, $body),
                'DELETE' => $request->delete($path),
            };
        } catch (ConnectionException) {
            $this->log($operation, null, 'timeout');

            throw new CalendarTemporarilyUnavailable('Outlook reageert niet.');
        }

        if ($response->successful() || ($notFoundOk && $response->status() === 404)) {
            return $response;
        }

        $status = $response->status();
        $code = (string) $response->json('error.code', '');
        $this->log($operation, $status, $code);

        if ($status === 401) {
            // Token verlopen of ingetrokken: volgende poging haalt een nieuw token op.
            Cache::forget(self::TOKEN_CACHE_KEY);

            throw new CalendarTemporarilyUnavailable('Outlook vroeg om opnieuw aanmelden.');
        }

        if ($status === 429 || $status >= 500) {
            $retryAfter = $response->header('Retry-After');

            throw new CalendarTemporarilyUnavailable(
                'Outlook is tijdelijk niet beschikbaar.',
                is_numeric($retryAfter) ? (int) $retryAfter : null,
            );
        }

        throw new CalendarRejected("Outlook weigerde: {$operation}.");
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds);
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if ($this->tenantId === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new CalendarRejected('De Outlook-koppeling is niet ingesteld.');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout($this->timeoutSeconds)
                ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException) {
            $this->log('aanmelden', null, 'timeout');

            throw new CalendarTemporarilyUnavailable('Microsoft-aanmelding reageert niet.');
        }

        if ($response->status() === 429 || $response->serverError()) {
            $this->log('aanmelden', $response->status(), (string) $response->json('error', ''));

            throw new CalendarTemporarilyUnavailable('Microsoft-aanmelding is tijdelijk niet beschikbaar.');
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            // Meestal een verlopen of verkeerd client-secret, of ontbrekende admin consent.
            $this->log('aanmelden', $response->status(), (string) $response->json('error', ''));

            throw new CalendarRejected('Aanmelden bij Microsoft is mislukt; controleer de koppelinginstellingen.');
        }

        // Ruim vóór het verlopen vernieuwen.
        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * @return array{dateTime: string, timeZone: string}
     */
    private function dateTime(CarbonImmutable $moment): array
    {
        return [
            'dateTime' => $moment->setTimezone($this->timezone)->format('Y-m-d\TH:i:s'),
            'timeZone' => $this->timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendee(CalendarAttendee $attendee, string $type): array
    {
        return [
            'emailAddress' => ['address' => $attendee->email, 'name' => $attendee->name],
            'type' => $type,
        ];
    }

    private function meetingResult(Response $response): CalendarMeetingResult
    {
        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            $this->log('afspraak lezen', $response->status(), 'missingId');

            throw new CalendarTemporarilyUnavailable('Outlook gaf een onverwacht antwoord.');
        }

        return new CalendarMeetingResult(
            $id,
            $response->json('iCalUId'),
            $response->json('onlineMeeting.joinUrl'),
        );
    }

    /**
     * @param  array{dateTime?: string, timeZone?: string}  $value
     */
    private function parseGraphTime(array $value): CarbonImmutable
    {
        $zone = $value['timeZone'] ?? 'UTC';

        try {
            $parsed = CarbonImmutable::parse($value['dateTime'] ?? '', $zone);
        } catch (Throwable) {
            // Onbekende (Windows-)tijdzonenaam: we vroegen UTC, dus behandel het als UTC.
            $parsed = CarbonImmutable::parse($value['dateTime'] ?? '', 'UTC');
        }

        return $parsed->setTimezone(config('app.timezone'));
    }

    private function log(string $operation, ?int $status, string $code): void
    {
        Log::warning('Outlook-koppeling: aanroep mislukt', [
            'operation' => $operation,
            'status' => $status,
            'code' => $code,
        ]);
    }
}
