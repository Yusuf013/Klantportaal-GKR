<?php

namespace Tests\Feature;

use App\Services\OutlookCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Test het lezen van een gepubliceerde Outlook-agenda ZONDER echt naar
 * Microsoft te gaan: Http::fake() doet alsof Outlook antwoordt.
 *
 * De nep-agenda hieronder heeft dezelfde opbouw als de echte link van Outlook
 * (Windows-namen voor tijdzones, alleen Bezet / Voorlopig / Beschikbaar).
 */
class OutlookCalendarTest extends TestCase
{
    // Verzonnen link. "geheim123" gebruiken we om te controleren dat de link nergens uitlekt.
    private const URL = 'https://outlook.office365.com/owa/calendar/test@example.com/geheim123/calendar.ics';

    protected function setUp(): void
    {
        parent::setUp();

        // Vaste instellingen, wat er ook in .env staat
        config(['services.outlook' => [
            'cache_minutes' => 5,
            'days_ahead'    => 365,
            'allowed_hosts' => ['outlook.office365.com', 'outlook.office.com'],
            'busy_statuses' => ['BUSY', 'OOF', 'TENTATIVE'],
            'test_ics_url'  => null,
        ]]);

        Cache::flush();

        // "Vandaag" is in deze tests altijd maandag 5 oktober 2026, 08:00 Nederlandse tijd
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00', 'Europe/Amsterdam'));
    }

    /**
     * Eén afspraak in het formaat van Outlook.
     * $start en $end: "20261013T140000" (met tijd) of "20261015" (hele dag).
     * $timezone leeg = geen tijdzone in de afspraak.
     */
    private function event(string $uid, string $start, string $end, string $busyStatus = 'BUSY', array $extra = [], string $timezone = 'W. Europe Standard Time'): string
    {
        $allDay = strlen($start) === 8;
        $prefix = match (true) {
            $allDay          => ';VALUE=DATE',
            $timezone === '' => '',
            default          => ";TZID={$timezone}",
        };

        return implode("\r\n", [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            'SUMMARY:Bezet',
            "DTSTART{$prefix}:{$start}",
            "DTEND{$prefix}:{$end}",
            'DTSTAMP:20261001T080000Z',
            "X-MICROSOFT-CDO-BUSYSTATUS:{$busyStatus}",
            ...$extra,
            'END:VEVENT',
        ]);
    }

    private function calendar(string ...$events): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'METHOD:PUBLISH',
            'PRODID:Microsoft Exchange Server 2010',
            'VERSION:2.0',
            'BEGIN:VTIMEZONE',
            'TZID:W. Europe Standard Time',
            'BEGIN:STANDARD',
            'DTSTART:16010101T030000',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10',
            'END:STANDARD',
            'BEGIN:DAYLIGHT',
            'DTSTART:16010101T020000',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3',
            'END:DAYLIGHT',
            'END:VTIMEZONE',
            ...$events,
            'END:VCALENDAR',
        ]) . "\r\n";
    }

    private function fakeOutlook(string ...$events): void
    {
        Http::fake([
            'outlook.office365.com/*' => Http::response($this->calendar(...$events), 200, ['Content-Type' => 'text/calendar']),
        ]);
    }

    /**
     * Bezette momenten tussen twee Nederlandse tijden, als leesbare tekst.
     */
    private function busy(string $from, string $to): array
    {
        $intervals = app(OutlookCalendar::class)->busyIntervals(
            self::URL,
            CarbonImmutable::parse($from, 'Europe/Amsterdam'),
            CarbonImmutable::parse($to, 'Europe/Amsterdam'),
        );

        return array_map(
            fn ($interval) => $interval['start']->format('Y-m-d H:i') . ' - ' . $interval['end']->format('Y-m-d H:i'),
            $intervals
        );
    }

    public function test_bezette_afspraak_staat_op_de_juiste_nederlandse_tijd(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000'));

        $this->assertSame(['2026-10-13 14:00 - 2026-10-13 15:00'], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_zomertijd_en_wintertijd_worden_goed_omgerekend(): void
    {
        $this->fakeOutlook(
            $this->event('zomer', '20261013T140000', '20261013T150000'),
            $this->event('winter', '20261110T140000', '20261110T150000'),
        );

        $intervals = app(OutlookCalendar::class)->busyIntervals(
            self::URL,
            CarbonImmutable::parse('2026-10-01', 'Europe/Amsterdam'),
            CarbonImmutable::parse('2026-12-01', 'Europe/Amsterdam'),
        );

        $this->assertCount(2, $intervals);
        // In de zomer loopt Nederland 2 uur voor op UTC, in de winter 1 uur
        $this->assertSame('12:00', $intervals[0]['start']->utc()->format('H:i'));
        $this->assertSame('13:00', $intervals[1]['start']->utc()->format('H:i'));
    }

    public function test_afspraak_in_britse_tijd_wordt_omgerekend_naar_nederlandse_tijd(): void
    {
        // 13:00 in Londen is 14:00 in Nederland
        $this->fakeOutlook($this->event('a', '20261013T130000', '20261013T140000', 'BUSY', [], 'GMT Standard Time'));

        $this->assertSame(['2026-10-13 14:00 - 2026-10-13 15:00'], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_beschikbaar_telt_niet_als_bezet(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000', 'FREE'));

        $this->assertSame([], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_voorlopig_telt_standaard_als_bezet(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000', 'TENTATIVE'));

        $this->assertCount(1, $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_voorlopig_kan_in_de_instellingen_worden_uitgezet(): void
    {
        config(['services.outlook.busy_statuses' => ['BUSY', 'OOF']]);
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000', 'TENTATIVE'));

        $this->assertSame([], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_hele_dag_afwezig_blokkeert_de_hele_dag(): void
    {
        $this->fakeOutlook($this->event('vrij', '20261015', '20261016', 'OOF'));

        $this->assertSame(['2026-10-15 00:00 - 2026-10-16 00:00'], $this->busy('2026-10-15 09:00', '2026-10-15 10:00'));
        // De dag ervoor en erna zijn gewoon vrij
        $this->assertSame([], $this->busy('2026-10-14 15:00', '2026-10-14 16:00'));
        $this->assertSame([], $this->busy('2026-10-16 09:00', '2026-10-16 10:00'));
    }

    public function test_hele_dag_beschikbaar_blokkeert_niets(): void
    {
        // Bijvoorbeeld een verjaardag of feestdag
        $this->fakeOutlook($this->event('verjaardag', '20261015', '20261016', 'FREE'));

        $this->assertSame([], $this->busy('2026-10-15 00:00', '2026-10-16 00:00'));
    }

    public function test_wekelijkse_afspraak_wordt_uitgerekend_en_uitzondering_overgeslagen(): void
    {
        // Elke dinsdag 10:00, vier keer: 6, 13, 20 en 27 oktober. De 20e is verwijderd.
        // 27 oktober valt na het ingaan van de wintertijd en moet 10:00 blijven.
        $this->fakeOutlook($this->event('reeks', '20261006T100000', '20261006T110000', 'BUSY', [
            'RRULE:FREQ=WEEKLY;COUNT=4;BYDAY=TU',
            'EXDATE;TZID=W. Europe Standard Time:20261020T100000',
        ]));

        $this->assertSame([
            '2026-10-06 10:00 - 2026-10-06 11:00',
            '2026-10-13 10:00 - 2026-10-13 11:00',
            '2026-10-27 10:00 - 2026-10-27 11:00',
        ], $this->busy('2026-10-05 00:00', '2026-11-30 00:00'));
    }

    public function test_reeks_die_lang_geleden_begon_telt_nog_steeds_mee(): void
    {
        // Elke dinsdag sinds januari, zonder einddatum
        $this->fakeOutlook($this->event('reeks', '20260106T100000', '20260106T110000', 'BUSY', [
            'RRULE:FREQ=WEEKLY;BYDAY=TU',
        ]));

        $this->assertSame(['2026-10-13 10:00 - 2026-10-13 11:00'], $this->busy('2026-10-12 00:00', '2026-10-17 00:00'));
    }

    public function test_verplaatste_afspraak_uit_een_reeks_staat_op_het_nieuwe_moment(): void
    {
        // Elke dinsdag 10:00 (6, 13 en 20 oktober). Die van de 13e is verzet naar 15:00.
        $this->fakeOutlook(
            $this->event('reeks', '20261006T100000', '20261006T110000', 'BUSY', [
                'RRULE:FREQ=WEEKLY;COUNT=3;BYDAY=TU',
            ]),
            $this->event('reeks', '20261013T150000', '20261013T160000', 'BUSY', [
                'RECURRENCE-ID;TZID=W. Europe Standard Time:20261013T100000',
            ]),
        );

        $this->assertSame([
            '2026-10-06 10:00 - 2026-10-06 11:00',
            '2026-10-13 15:00 - 2026-10-13 16:00',
            '2026-10-20 10:00 - 2026-10-20 11:00',
        ], $this->busy('2026-10-05 00:00', '2026-11-30 00:00'));
    }

    public function test_wekelijkse_vrije_dag_blokkeert_elke_week_de_hele_dag(): void
    {
        // Elke vrijdag afwezig, twee keer: 9 en 16 oktober
        $this->fakeOutlook($this->event('vrijdag', '20261009', '20261010', 'OOF', [
            'RRULE:FREQ=WEEKLY;COUNT=2;BYDAY=FR',
        ]));

        $this->assertSame([
            '2026-10-09 00:00 - 2026-10-10 00:00',
            '2026-10-16 00:00 - 2026-10-17 00:00',
        ], $this->busy('2026-10-05 00:00', '2026-11-30 00:00'));
    }

    public function test_afspraak_zonder_tijdzone_geldt_als_nederlandse_tijd(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000', 'BUSY', [], ''));

        $this->assertSame(['2026-10-13 14:00 - 2026-10-13 15:00'], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_afspraak_in_utc_wordt_omgerekend_naar_nederlandse_tijd(): void
    {
        // De Z achter de tijd betekent UTC. 12:00 UTC is in oktober 14:00 in Nederland.
        $this->fakeOutlook($this->event('a', '20261013T120000Z', '20261013T130000Z', 'BUSY', [], ''));

        $this->assertSame(['2026-10-13 14:00 - 2026-10-13 15:00'], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_geannuleerde_afspraak_telt_niet_mee(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000', 'BUSY', ['STATUS:CANCELLED']));

        $this->assertSame([], $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }

    public function test_alleen_afspraken_in_het_gevraagde_tijdvak(): void
    {
        $this->fakeOutlook(
            $this->event('a', '20261013T140000', '20261013T150000'),
            $this->event('b', '20261014T090000', '20261014T100000'),
        );

        $this->assertSame(['2026-10-13 14:00 - 2026-10-13 15:00'], $this->busy('2026-10-13 14:00', '2026-10-13 15:00'));
        // Een afspraak die precies aansluit, overlapt niet
        $this->assertSame([], $this->busy('2026-10-13 15:00', '2026-10-13 16:00'));
        $this->assertSame([], $this->busy('2026-10-13 13:00', '2026-10-13 14:00'));
    }

    public function test_agenda_wordt_onthouden_en_niet_bij_elke_vraag_opgehaald(): void
    {
        $this->fakeOutlook($this->event('a', '20261013T140000', '20261013T150000'));

        $this->busy('2026-10-13 09:00', '2026-10-13 10:00');
        $this->busy('2026-10-13 14:00', '2026-10-13 15:00');
        $this->busy('2026-10-14 09:00', '2026-10-14 10:00');

        Http::assertSentCount(1);
    }

    public function test_link_naar_een_ander_adres_wordt_geweigerd_zonder_iets_op_te_halen(): void
    {
        Http::fake();

        $wrongLinks = [
            'http://outlook.office365.com/owa/calendar/x/calendar.ics',          // geen https
            'https://evil.example.com/calendar.ics',                              // ander adres
            'https://outlook.office365.com@evil.example.com/calendar.ics',        // vermomd als Outlook
            'https://outlook.office365.com.evil.example.com/calendar.ics',        // lijkt op Outlook
            'https://outlook.office365.com:8443/owa/calendar/x/calendar.ics',     // afwijkende poort
            'javascript:alert(1)',
            '',
        ];

        foreach ($wrongLinks as $link) {
            try {
                app(OutlookCalendar::class)->busyIntervals($link, now(), now()->addDay());
                $this->fail("Deze link had geweigerd moeten worden: {$link}");
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Dit is geen geldige Outlook-agendalink.', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_foutmelding_bij_onbereikbare_agenda_bevat_nooit_de_link(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::failedConnection()]);

        try {
            $this->busy('2026-10-13 00:00', '2026-10-14 00:00');
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('niet bereikbaar', $e->getMessage());
            $this->assertStringNotContainsString('geheim123', $e->getMessage());
            $this->assertStringNotContainsString('outlook.office365.com', $e->getMessage());
        }
    }

    public function test_fout_van_outlook_geeft_een_duidelijke_melding(): void
    {
        Http::fake(['outlook.office365.com/*' => Http::response('Not found', 404)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('gaf een fout (404)');

        $this->busy('2026-10-13 00:00', '2026-10-14 00:00');
    }

    public function test_antwoord_dat_geen_agenda_is_wordt_geweigerd(): void
    {
        // Bijvoorbeeld een inlogpagina
        Http::fake(['outlook.office365.com/*' => Http::response('<html><body>Sign in</body></html>')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('gaf geen agenda terug');

        $this->busy('2026-10-13 00:00', '2026-10-14 00:00');
    }

    public function test_mislukte_poging_wordt_niet_onthouden(): void
    {
        Http::fake([
            'outlook.office365.com/*' => Http::sequence()
                ->push('Storing', 503)
                ->push($this->calendar($this->event('a', '20261013T140000', '20261013T150000')), 200),
        ]);

        try {
            $this->busy('2026-10-13 00:00', '2026-10-14 00:00');
            $this->fail('Er had een fout moeten komen.');
        } catch (RuntimeException) {
            // verwacht
        }

        // De tweede keer lukt het wel: de fout is niet in de cache gezet
        $this->assertCount(1, $this->busy('2026-10-13 00:00', '2026-10-14 00:00'));
    }
}