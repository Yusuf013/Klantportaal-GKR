<?php

namespace Tests\Feature\Calendar;

use App\Services\Calendar\BusyKind;
use App\Services\Calendar\CalendarAttendee;
use App\Services\Calendar\CalendarCategory;
use App\Services\Calendar\CalendarMeeting;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use App\Services\Calendar\GraphCalendarProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * De Graph-adapter tegen nagebootste Microsoft-antwoorden (Http::fake): er gaat nooit een echte
 * aanroep naar buiten (skill `external-integration`, ADR-011).
 */
class GraphCalendarProviderTest extends TestCase
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token';

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function provider(): GraphCalendarProvider
    {
        return new GraphCalendarProvider('tenant-id', 'client-id', 'secret', self::GRAPH, 10, 'Europe/Amsterdam');
    }

    private function meeting(?string $existingId = null, bool $online = true): CalendarMeeting
    {
        $start = CarbonImmutable::parse('2026-10-12 14:00', 'Europe/Amsterdam');

        return new CalendarMeeting(
            organizerEmail: 'owen@gkr.nl',
            subject: 'Kwartaalreview',
            body: 'Project: Website',
            start: $start,
            end: $start->addHour(),
            location: 'Microsoft Teams-vergadering',
            online: $online,
            requiredAttendees: [new CalendarAttendee('klant@example.com', 'Bakkerij Jansen')],
            optionalAttendees: [new CalendarAttendee('info@gkr.nl', 'GKR agenda')],
            idempotencyKey: 'klantportaal-appointment-7',
            existingId: $existingId,
        );
    }

    private function fakeToken(): array
    {
        return [self::TOKEN_URL => Http::response(['access_token' => 'token-123', 'expires_in' => 3600])];
    }

    public function test_it_creates_a_teams_meeting_in_the_organizers_calendar_with_attendees(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/users/owen%40gkr.nl/events' => Http::response([
                'id' => 'evt-1', 'iCalUId' => 'ical-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/abc'],
            ], 201),
        ]);

        $result = $this->provider()->upsertMeeting($this->meeting());

        $this->assertSame('evt-1', $result->externalId);
        $this->assertSame('ical-1', $result->iCalUId);
        $this->assertSame('https://teams.microsoft.com/l/meetup-join/abc', $result->joinUrl);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::GRAPH.'/users/owen%40gkr.nl/events') {
                return false;
            }

            return $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer token-123')
                && $request['transactionId'] === 'klantportaal-appointment-7'
                && $request['isOnlineMeeting'] === true
                && $request['onlineMeetingProvider'] === 'teamsForBusiness'
                && $request['start'] === ['dateTime' => '2026-10-12T14:00:00', 'timeZone' => 'Europe/Amsterdam']
                && $request['attendees'][0] === ['emailAddress' => ['address' => 'klant@example.com', 'name' => 'Bakkerij Jansen'], 'type' => 'required']
                && $request['attendees'][1]['type'] === 'optional';
        });
    }

    public function test_the_token_is_cached_between_calls(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/*' => Http::response(['id' => 'evt-1', 'iCalUId' => 'ical-1'], 201),
        ]);

        $this->provider()->upsertMeeting($this->meeting());
        $this->provider()->upsertMeeting($this->meeting());

        Http::assertSentCount(3); // 1x token, 2x event
    }

    public function test_an_update_of_a_meeting_deleted_in_outlook_recreates_it(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/users/owen%40gkr.nl/events/evt-old' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
            self::GRAPH.'/users/owen%40gkr.nl/events' => Http::response(['id' => 'evt-new', 'iCalUId' => 'ical-2'], 201),
        ]);

        $this->assertSame('evt-new', $this->provider()->upsertMeeting($this->meeting('evt-old'))->externalId);
    }

    public function test_rate_limiting_is_a_temporary_problem_with_retry_after(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/*' => Http::response(['error' => ['code' => 'TooManyRequests']], 429, ['Retry-After' => '17']),
        ]);

        try {
            $this->provider()->upsertMeeting($this->meeting());
            $this->fail('verwachtte een tijdelijke fout');
        } catch (CalendarTemporarilyUnavailable $e) {
            $this->assertSame(17, $e->retryAfterSeconds);
        }
    }

    public function test_server_errors_and_timeouts_are_temporary(): void
    {
        Http::fake($this->fakeToken() + [self::GRAPH.'/*' => Http::response([], 503)]);
        $this->expectException(CalendarTemporarilyUnavailable::class);
        $this->provider()->upsertMeeting($this->meeting());
    }

    public function test_a_timeout_is_temporary(): void
    {
        Http::fake($this->fakeToken() + [self::GRAPH.'/*' => fn () => throw new ConnectionException('timed out')]);
        $this->expectException(CalendarTemporarilyUnavailable::class);
        $this->provider()->upsertMeeting($this->meeting());
    }

    public function test_missing_permissions_are_a_permanent_problem(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access to OData is disabled for owen@gkr.nl']], 403),
        ]);

        try {
            $this->provider()->upsertMeeting($this->meeting());
            $this->fail('verwachtte een blijvende fout');
        } catch (CalendarRejected $e) {
            // Geen vendor-bericht of e-mailadres in de foutmelding.
            $this->assertStringNotContainsString('owen@gkr.nl', $e->getMessage());
            $this->assertStringNotContainsString('OData', $e->getMessage());
        }
    }

    public function test_an_invalid_client_secret_is_a_permanent_problem(): void
    {
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_client'], 401)]);
        $this->expectException(CalendarRejected::class);
        $this->provider()->upsertMeeting($this->meeting());
    }

    public function test_a_401_from_graph_drops_the_cached_token_and_is_retried_later(): void
    {
        Http::fake($this->fakeToken() + [self::GRAPH.'/*' => Http::response([], 401)]);

        try {
            $this->provider()->upsertMeeting($this->meeting());
        } catch (CalendarTemporarilyUnavailable) {
        }

        $this->assertFalse(Cache::has('calendar.graph.token'));
    }

    public function test_an_unexpected_response_without_id_is_temporary(): void
    {
        Http::fake($this->fakeToken() + [self::GRAPH.'/*' => Http::response(['unexpected' => true], 201)]);
        $this->expectException(CalendarTemporarilyUnavailable::class);
        $this->provider()->upsertMeeting($this->meeting());
    }

    public function test_busy_periods_are_translated_to_domain_terms(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/users/owen%40gkr.nl/calendar/getSchedule' => Http::response(['value' => [
                ['scheduleId' => 'Owen@gkr.nl', 'scheduleItems' => [
                    ['status' => 'busy', 'start' => ['dateTime' => '2026-10-12T08:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-10-12T09:00:00.0000000', 'timeZone' => 'UTC']],
                    ['status' => 'oof', 'start' => ['dateTime' => '2026-10-13T00:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-10-14T00:00:00.0000000', 'timeZone' => 'UTC']],
                    ['status' => 'tentative', 'start' => ['dateTime' => '2026-10-12T12:00:00.0000000', 'timeZone' => 'W. Europe Standard Time'], 'end' => ['dateTime' => '2026-10-12T13:00:00.0000000', 'timeZone' => 'W. Europe Standard Time']],
                    ['status' => 'free', 'start' => ['dateTime' => '2026-10-12T14:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-10-12T15:00:00.0000000', 'timeZone' => 'UTC']],
                ]],
            ]]),
        ]);

        $from = CarbonImmutable::parse('2026-10-12', 'Europe/Amsterdam');
        $periods = $this->provider()->busyPeriods(['owen@gkr.nl'], $from, $from->addDays(2))['owen@gkr.nl'];

        $this->assertCount(3, $periods);
        $this->assertSame(BusyKind::Busy, $periods[0]->kind);
        $this->assertSame('2026-10-12 10:00', $periods[0]->start->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'));
        $this->assertSame(BusyKind::Away, $periods[1]->kind);
        $this->assertSame(BusyKind::Tentative, $periods[2]->kind);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/getSchedule')
            && $r['schedules'] === ['owen@gkr.nl']
            && $r['startTime'] === ['dateTime' => '2026-10-11T22:00:00', 'timeZone' => 'UTC']
            && $r->hasHeader('Prefer', 'outlook.timezone="UTC"'));
    }

    public function test_an_unreadable_mailbox_in_the_schedule_is_a_permanent_problem(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/*' => Http::response(['value' => [
                ['scheduleId' => 'owen@gkr.nl', 'error' => ['message' => 'mailbox not found', 'responseCode' => 'ErrorMailRecipientNotFound']],
            ]]),
        ]);

        $this->expectException(CalendarRejected::class);
        $from = CarbonImmutable::parse('2026-10-12', 'Europe/Amsterdam');
        $this->provider()->busyPeriods(['owen@gkr.nl'], $from, $from->addDay());
    }

    public function test_marking_in_the_overview_accepts_silently_and_sets_colour_categories(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/users/info%40gkr.nl/events?*' => Http::response(['value' => [['id' => 'copy-1']]]),
            self::GRAPH.'/users/info%40gkr.nl/events/copy-1/accept' => Http::response([], 202),
            self::GRAPH.'/users/info%40gkr.nl/outlook/masterCategories' => Http::sequence()
                ->push(['value' => [['id' => 'cat-1', 'displayName' => 'Noah', 'color' => 'preset4']]])
                ->push(['id' => 'cat-2', 'displayName' => 'Owen', 'color' => 'preset7'], 201),
            self::GRAPH.'/users/info%40gkr.nl/events/copy-1' => Http::response(['id' => 'copy-1']),
        ]);

        $found = $this->provider()->markInOverview('info@gkr.nl', "ical-'1", [
            new CalendarCategory('Owen', 'preset7'),
            new CalendarCategory('Noah', 'preset4'),
        ]);

        $this->assertTrue($found);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'iCalUId') && str_contains(urldecode($r->url()), "iCalUId eq 'ical-''1'"));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/accept') && $r['sendResponse'] === false);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/masterCategories') && $r['displayName'] === 'Owen' && $r['color'] === 'preset7');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['categories'] === ['Owen', 'Noah']);
    }

    public function test_marking_in_the_overview_reports_when_the_copy_has_not_arrived_yet(): void
    {
        Http::fake($this->fakeToken() + [self::GRAPH.'/users/info%40gkr.nl/events?*' => Http::response(['value' => []])]);

        $this->assertFalse($this->provider()->markInOverview('info@gkr.nl', 'ical-1', []));
    }

    public function test_it_refuses_to_run_without_configuration(): void
    {
        $this->expectException(CalendarRejected::class);
        (new GraphCalendarProvider('', '', '', self::GRAPH, 10, 'Europe/Amsterdam'))->upsertMeeting($this->meeting());
    }

    public function test_check_mailboxes_reports_ok_errors_and_missing_mailboxes_separately(): void
    {
        Http::fake($this->fakeToken() + [
            self::GRAPH.'/users/owen%40gkr.nl/calendar/getSchedule' => Http::response(['value' => [
                ['scheduleId' => 'Owen@gkr.nl', 'availabilityView' => '0000', 'scheduleItems' => []],
                ['scheduleId' => 'bo@gkr.nl', 'error' => ['message' => 'Geen toegang', 'responseCode' => 'ErrorAccessDenied']],
            ]]),
        ]);

        $result = $this->provider()->checkMailboxes(['owen@gkr.nl', 'bo@gkr.nl', 'noah@gkr.nl']);

        // Een geslaagde mailbox (null) mag niet als "ontbrekend" worden overschreven.
        $this->assertSame([
            'owen@gkr.nl' => null,
            'bo@gkr.nl' => 'ErrorAccessDenied',
            'noah@gkr.nl' => 'niet teruggekregen van Outlook',
        ], $result);
    }
}
