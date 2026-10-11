<?php

namespace App\Console\Commands;

use App\Models\AdAccount;
use App\Services\GoogleAdsService;
use App\Services\GoogleAnalyticsService;
use App\Services\MetaAdsService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Haalt de marketingcijfers alvast op en zet ze klaar.
 *
 * Waarom: een pagina met cijfers moest wachten tot Google of Meta antwoord gaf, en dat
 * duurt een paar seconden. Dit commando doet dat wachten op de achtergrond. De server
 * draait het om de paar uur, zodat er altijd een vers rapport klaarligt en de pagina
 * direct laadt.
 *
 * Veilig om vaker te draaien: het leest alleen, en als iets mislukt blijft alles werken
 * zoals voorheen (de pagina haalt de cijfers dan zelf op).
 */
class WarmMarketingCache extends Command
{
    protected $signature = 'marketing:warm';

    protected $description = 'Haalt de cijfers van Google Ads, Meta en Google Analytics alvast op, zodat de pagina\'s direct laden';

    public function handle(GoogleAdsService $googleAds, MetaAdsService $meta, GoogleAnalyticsService $analytics): int
    {
        $this->warmAdAccounts(
            'Google Ads',
            AdAccount::PLATFORM_GOOGLE_ADS,
            $googleAds->usesFakeData(),
            GoogleAdsService::PERIODS,
            fn (AdAccount $account, int $days) => $googleAds->refresh($account, $days)
        );

        $this->warmAdAccounts(
            'Meta',
            AdAccount::PLATFORM_META,
            $meta->usesFakeData(),
            MetaAdsService::PERIODS,
            fn (AdAccount $account, int $days) => $meta->refresh($account, $days)
        );

        $this->warmAnalytics($analytics);

        // Altijd "gelukt": een mislukt account staat hierboven en in de log, en de
        // pagina's blijven werken. Zo meldt Railway niet elke keer een crash.
        return self::SUCCESS;
    }

    /**
     * Alle gekoppelde accounts van één platform, voor elke periode.
     */
    private function warmAdAccounts(string $label, string $platform, bool $fake, array $periods, Closure $refresh): void
    {
        // Nepdata maakt het portaal zelf, zonder wachttijd. Er valt dan niets op te halen.
        if ($fake) {
            $this->line("{$label}: staat op nepdata, overgeslagen.");

            return;
        }

        $accounts = AdAccount::where('platform', $platform)->orderBy('id')->get();

        $ready = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            foreach ($periods as $days) {
                try {
                    $refresh($account, $days);
                    $ready++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->reportFailure($label, $e, ['ad_account_id' => $account->id, 'days' => $days]);

                    // Lukt één periode niet, dan de andere van dit account waarschijnlijk ook
                    // niet. Die slaan we over, zodat we Google of Meta niet onnodig belasten.
                    continue 2;
                }
            }
        }

        $this->info("{$label}: {$ready} rapporten klaargezet, {$failed} accounts mislukt (van {$accounts->count()}).");
    }

    /**
     * De websitecijfers van GKR zelf (één Analytics-property), voor elke periode.
     */
    private function warmAnalytics(GoogleAnalyticsService $analytics): void
    {
        if (! $analytics->isConfigured()) {
            $this->line('Google Analytics: niet ingesteld, overgeslagen.');

            return;
        }

        $ready = 0;

        foreach (array_keys(GoogleAnalyticsService::PERIODS) as $days) {
            try {
                $analytics->refreshDashboard($days);
                $ready++;
            } catch (Throwable $e) {
                $this->reportFailure('Google Analytics', $e, ['days' => $days]);
                $this->warn("Google Analytics: {$ready} rapporten klaargezet, daarna gestopt.");

                return;
            }
        }

        $this->info("Google Analytics: {$ready} rapporten klaargezet.");
    }

    /**
     * Een mislukte poging: kort op het scherm, met de details in de log.
     * De meldingen van de services bevatten nooit een token of sleutel.
     */
    private function reportFailure(string $label, Throwable $e, array $context): void
    {
        Log::warning("{$label}: cijfers klaarzetten mislukt", $context + ['message' => $e->getMessage()]);

        $this->warn("{$label}: ophalen mislukt. " . $e->getMessage());
    }
}