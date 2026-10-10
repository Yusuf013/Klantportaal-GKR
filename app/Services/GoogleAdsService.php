<?php

namespace App\Services;

use App\Models\AdAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Haalt advertentiecijfers op voor één Google Ads-account.
 *
 * Met GOOGLE_ADS_FAKE=true komt er nepdata terug, met false de echte cijfers
 * uit de Google Ads API. Alleen fetchCampaignDailyRows() verschilt tussen die
 * twee; de rest (periodes, totalen, trends, campagnes, cache) is hetzelfde.
 *
 * Echte cijfers: het portaal logt in als het robotaccount (zelfde sleutel als
 * Google Analytics). Dat account is bij elk Google Ads-account toegevoegd met
 * alleen leesrechten. Er is geen developer token meer nodig: sinds september
 * 2026 hangt de toegang aan het Google Cloud-project.
 *
 * Het rapport heeft bewust dezelfde opbouw als dat van MetaAdsService
 * (totals, previous, trends, series, campaigns), zodat beide hetzelfde
 * weergaveblok gebruiken.
 */
class GoogleAdsService
{
    public const PERIODS = [7, 28, 90, 365];
    public const DEFAULT_PERIOD = 28;

    private const METRICS = ['impressions', 'clicks', 'conversions', 'spend'];

    // Het enige toegangsbereik dat Google Ads kent. Lezen of schrijven regel je niet hier,
    // maar met de rol van het robotaccount in Google Ads (bij ons: Alleen lezen).
    private const SCOPE = 'https://www.googleapis.com/auth/adwords';

    private const API_URL = 'https://googleads.googleapis.com';

    // Google geeft per keer hooguit 10.000 rijen. Meer pagina's dan dit verwachten we nooit.
    private const MAX_PAGES = 20;

    // De toegangscode van Google, onthouden zolang dit ene verzoek aan het portaal duurt
    private ?string $token = null;

    public function __construct(private GoogleAccessToken $accessToken)
    {
    }

    // Namen voor de nepdata. Echte campagnenamen komen uit Google Ads.
    private const FAKE_CAMPAIGNS = [
        'Zoeken - Merknaam',
        'Zoeken - Producten',
        'Zoeken - Diensten',
        'Performance Max',
        'Display - Remarketing',
    ];

    /**
     * Let op: deze methode vraagt bewust een AdAccount-model en geen los nummer.
     * Zo moet de aanroeper het account altijd uit de database halen
     * (bijv. via auth()->user()->googleAdsAccount) en nooit uit de URL.
     */
    public function getInsights(AdAccount $account, int $days): array
    {
        if ($account->platform !== AdAccount::PLATFORM_GOOGLE_ADS) {
            throw new InvalidArgumentException('Dit is geen Google Ads-account.');
        }

        // Onbekende periode? Dan standaard 28 dagen (zelfde als bij GA en Meta)
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;

        // Einddatum is gisteren: de cijfers van vandaag zijn nog niet compleet
        $end = CarbonImmutable::yesterday();

        // Nepdata en echte data krijgen een eigen cachesleutel, zodat je bij
        // het omschakelen nooit nog oude nepcijfers ziet
        $mode = $this->usesFakeData() ? 'fake' : 'live';
        $cacheKey = "google_ads:{$mode}:{$account->account_id}:{$days}:{$end->toDateString()}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes((int) config('services.google_ads.cache_minutes', 180)),
            fn () => $this->buildReport($account->account_id, $days, $end)
        );
    }

    public function usesFakeData(): bool
    {
        return (bool) config('services.google_ads.fake', true);
    }

    /**
     * Google toont klantnummers als 123-456-7890. Wij slaan alleen de cijfers op.
     */
    public static function normalizeCustomerId(string $value): string
    {
        return preg_replace('/[\s-]/', '', trim($value));
    }

    private function buildReport(string $accountId, int $days, CarbonImmutable $end): array
    {
        // Huidige periode en een even lange periode ervoor (voor de trend)
        $start = $end->subDays($days - 1);
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);

        $currentRows = $this->fetchCampaignDailyRows($accountId, $start, $end);
        $previousRows = $this->fetchCampaignDailyRows($accountId, $previousStart, $previousEnd);

        // Per dag optellen over alle campagnes = de cijfers van het hele account
        $daily = $this->sumPerDay($currentRows, $start, $end);

        // Totalen rekenen we uit met de losse rijen en niet met de afgeronde dagen.
        // Google geeft conversies als kommagetal (bijv. 0,33): eerst per dag afronden
        // en dan optellen zou een ander totaal geven dan Google Ads zelf laat zien.
        $totals = $this->totals($currentRows);
        $previous = $this->totals($previousRows);

        $trends = [];
        foreach ($totals as $metric => $value) {
            $trends[$metric] = $this->percentChange($value, $previous[$metric]);
        }

        return [
            'is_fake'   => $this->usesFakeData(),
            'days'      => $days,
            'start'     => $start->toDateString(),
            'end'       => $end->toDateString(),
            'totals'    => $totals,
            'previous'  => $previous,
            'trends'    => $trends,
            // Bij 12 maanden per maand, anders per dag (net als bij GA en Meta)
            'series'    => $days === 365 ? $this->groupByMonth($daily) : $daily,
            'campaigns' => $this->campaignTotals($currentRows),
        ];
    }

    /**
     * Cijfers per campagne per dag. Elke rij:
     * date, campaign_id, campaign_name, impressions, clicks, conversions, spend (in euro's).
     */
    private function fetchCampaignDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($this->usesFakeData()) {
            return $this->fakeCampaignDailyRows($accountId, $start, $end);
        }

        return $this->liveCampaignDailyRows($accountId, $start, $end);
    }

    /**
     * Echte cijfers uit de Google Ads API, per campagne per dag.
     *
     * We stellen één vraag in de vraagtaal van Google Ads (GAQL). Google geeft
     * alleen rijen terug voor dagen waarop een campagne cijfers had.
     */
    private function liveCampaignDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        // Het klantnummer komt in het adres van het verzoek: alleen precies 10 cijfers toestaan
        if (! preg_match('/^\d{10}$/', $accountId)) {
            throw new InvalidArgumentException('Een Google Ads-klantnummer bestaat uit 10 cijfers.');
        }

        $version = (string) config('services.google_ads.api_version', 'v25');

        if (! preg_match('/^v\d+$/', $version)) {
            throw new RuntimeException('GOOGLE_ADS_API_VERSION is ongeldig. Verwacht bijvoorbeeld v25.');
        }

        $url = self::API_URL . "/{$version}/customers/{$accountId}/googleAds:search";

        $from = $start->toDateString();
        $to = $end->toDateString();

        // De datums maken we zelf (jaar-maand-dag). Er komt geen invoer van een gebruiker in de vraag.
        $query = 'SELECT campaign.id, campaign.name, segments.date, '
            . 'metrics.impressions, metrics.clicks, metrics.conversions, metrics.cost_micros '
            . "FROM campaign WHERE segments.date BETWEEN '{$from}' AND '{$to}'";

        $rows = [];
        $pageToken = null;

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $body = ['query' => $query];

            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            try {
                $response = $this->request()->post($url, $body);
            } catch (ConnectionException) {
                throw new RuntimeException('Google Ads is op dit moment niet bereikbaar.');
            }

            if ($response->failed()) {
                throw $this->apiError($response);
            }

            foreach ((array) $response->json('results', []) as $result) {
                $date = (string) data_get($result, 'segments.date');

                // Veiligheid: een rij buiten de gevraagde periode tellen we niet mee
                if ($date < $from || $date > $to) {
                    continue;
                }

                $rows[] = [
                    'date'          => $date,
                    'campaign_id'   => (string) data_get($result, 'campaign.id'),
                    'campaign_name' => (string) data_get($result, 'campaign.name', 'Campagne zonder naam'),
                    // Google stuurt grote gehele getallen als tekst ("1200"), vandaar (int)
                    'impressions'   => (int) data_get($result, 'metrics.impressions', 0),
                    'clicks'        => (int) data_get($result, 'metrics.clicks', 0),
                    'conversions'   => (float) data_get($result, 'metrics.conversions', 0),
                    // Google geeft kosten in micros: 1.000.000 micros = € 1
                    'spend'         => (int) data_get($result, 'metrics.costMicros', 0) / 1_000_000.0,
                ];
            }

            $pageToken = $response->json('nextPageToken');

            if (! $pageToken) {
                return $rows;
            }
        }

        // Liever een fout dan stilletjes onvolledige cijfers tonen
        throw new RuntimeException('Google Ads gaf meer gegevens terug dan verwacht. Er is niets getoond.');
    }

    /**
     * Een verzoek aan Google Ads, met de toegangscode van het robotaccount erin.
     */
    private function request(): PendingRequest
    {
        $this->token ??= $this->accessToken->forScope(self::SCOPE);

        $request = Http::withToken($this->token)
            ->timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->asJson();

        // Hangen de klantaccounts onder een beheeraccount van GKR? Dan wil Google
        // bij elk verzoek weten via welk beheeraccount we binnenkomen.
        $managerId = self::normalizeCustomerId((string) config('services.google_ads.login_customer_id'));

        if ($managerId !== '') {
            if (! preg_match('/^\d{10}$/', $managerId)) {
                throw new RuntimeException('GOOGLE_ADS_LOGIN_CUSTOMER_ID is ongeldig. Verwacht 10 cijfers.');
            }

            $request = $request->withHeaders(['login-customer-id' => $managerId]);
        }

        return $request;
    }

    /**
     * Maakt van een foutantwoord van Google een duidelijke melding voor de log.
     * De toegangscode staat hier nooit in. Tussen haakjes staat de code van Google,
     * handig om op te zoeken.
     */
    private function apiError(Response $response): RuntimeException
    {
        $status = $response->status();

        // Google Ads zet de precieze reden diep in het antwoord, bijv. USER_PERMISSION_DENIED
        $errorCode = data_get($response->json(), 'error.details.0.errors.0.errorCode');
        $code = is_array($errorCode) && $errorCode ? (string) reset($errorCode) : (string) $response->json('error.status', '');
        $code = preg_replace('/[^A-Z_]/', '', $code);

        $message = match (true) {
            $code === 'CLOUD_PROJECT_NOT_APPROVED_FOR_PRODUCTION'
                => 'Het Google Cloud-project heeft nog geen toegang tot echte Google Ads-accounts.',
            $code === 'CUSTOMER_NOT_ENABLED'
                => 'Dit Google Ads-account is niet actief.',
            $status === 401
                => 'Google Ads accepteert de inlog van het robotaccount niet.',
            $status === 403
                => 'Het robotaccount heeft geen toegang tot dit Google Ads-account. Voeg het daar toe als gebruiker met Alleen lezen.',
            $status === 429
                => 'De daglimiet van Google Ads is bereikt. Probeer het later opnieuw.',
            default
                => 'Google Ads gaf een fout.',
        };

        return new RuntimeException($message . ' (' . trim("{$status} {$code}") . ')');
    }

    /**
     * Nepdata die er realistisch uitziet en altijd hetzelfde is voor
     * hetzelfde account op dezelfde dag (zelfde idee als bij Meta).
     * Elk account krijgt 2 tot 4 vaste campagnes.
     */
    private function fakeCampaignDailyRows(string $accountId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $picker = new Randomizer(new Mt19937(crc32('campaigns|' . $accountId)));
        $campaigns = array_slice($picker->shuffleArray(self::FAKE_CAMPAIGNS), 0, $picker->getInt(2, 4));

        $rows = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            foreach ($campaigns as $index => $name) {
                $random = new Randomizer(new Mt19937(crc32($accountId . '|' . $index . '|' . $date->toDateString())));

                $impressions = $random->getInt(150, 1200);
                $clicks = intdiv($impressions * $random->getInt(15, 60), 1000);        // CTR tussen 1,5% en 6%
                // Google kan conversies verdelen over meerdere klikken, dus kommagetallen zijn normaal
                $conversions = round($clicks * $random->getInt(0, 800) / 10000, 2);   // 0% tot 8% van de klikken
                $spend = round($clicks * $random->getInt(40, 180) / 100, 2);          // € 0,40 tot € 1,80 per klik

                $rows[] = [
                    'date'          => $date->toDateString(),
                    'campaign_id'   => (string) ($index + 1),
                    'campaign_name' => $name,
                    'impressions'   => $impressions,
                    'clicks'        => $clicks,
                    'conversions'   => $conversions,
                    'spend'         => $spend,
                ];
            }
        }

        return $rows;
    }

    /**
     * Telt alle campagnes per dag op. Dagen zonder cijfers krijgen nullen,
     * zodat de grafiek geen gaten heeft.
     */
    private function sumPerDay(array $rows, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $days[$key] = ['date' => $key, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];
        }

        foreach ($rows as $row) {
            // Veiligheid: een rij buiten de periode tellen we niet mee
            if (! isset($days[$row['date']])) {
                continue;
            }

            foreach (self::METRICS as $metric) {
                $days[$row['date']][$metric] += $row[$metric];
            }
        }

        return array_values(array_map(
            fn ($day) => [...$day, 'conversions' => round($day['conversions'], 2), 'spend' => round($day['spend'], 2)],
            $days
        ));
    }

    /**
     * Totalen per campagne over de hele periode, hoogste kosten bovenaan.
     */
    private function campaignTotals(array $rows): array
    {
        $campaigns = [];

        foreach ($rows as $row) {
            $id = $row['campaign_id'];
            $campaigns[$id] ??= ['name' => $row['campaign_name'], 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];

            foreach (self::METRICS as $metric) {
                $campaigns[$id][$metric] += $row[$metric];
            }
        }

        $result = [];
        foreach ($campaigns as $campaign) {
            // Campagnes zonder vertoningen in deze periode laten we weg
            if ($campaign['impressions'] === 0) {
                continue;
            }

            $result[] = [
                'name'        => $campaign['name'],
                'impressions' => $campaign['impressions'],
                'clicks'      => $campaign['clicks'],
                'ctr'         => round($campaign['clicks'] / $campaign['impressions'] * 100, 2),
                'conversions' => round($campaign['conversions'], 2),
                'spend'       => round($campaign['spend'], 2),
            ];
        }

        usort($result, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $result;
    }

    private function totals(array $rows): array
    {
        $impressions = array_sum(array_column($rows, 'impressions'));
        $clicks = array_sum(array_column($rows, 'clicks'));

        return [
            'impressions' => $impressions,
            'clicks'      => $clicks,
            // CTR rekenen we zelf uit, zodat het altijd klopt met de getoonde klikken en vertoningen
            'ctr'         => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0,
            'conversions' => round(array_sum(array_column($rows, 'conversions')), 2),
            'spend'       => round(array_sum(array_column($rows, 'spend')), 2),
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        // Geen vorige waarde? Dan kun je geen percentage berekenen
        if ($previous == 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function groupByMonth(array $rows): array
    {
        $months = [];

        foreach ($rows as $row) {
            $month = substr($row['date'], 0, 7); // bijv. "2026-09"

            $months[$month] ??= ['date' => $month, 'impressions' => 0, 'clicks' => 0, 'conversions' => 0.0, 'spend' => 0.0];

            foreach (self::METRICS as $metric) {
                $months[$month][$metric] += $row[$metric];
            }
        }

        return array_values(array_map(
            fn ($month) => [...$month, 'conversions' => round($month['conversions'], 2), 'spend' => round($month['spend'], 2)],
            $months
        ));
    }
}