<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Vraagt bij Google een tijdelijke toegangscode aan voor het robotaccount.
 *
 * Het robotaccount logt niet in met een wachtwoord, maar met een sleutelbestand.
 * Met die sleutel vraagt het portaal een toegangscode aan die ongeveer een uur
 * geldig is. Die code gaat mee met elk verzoek aan Google. De sleutel zelf
 * verlaat het portaal nooit.
 *
 * Het is dezelfde sleutel als voor Google Analytics (GA_CREDENTIALS_PATH lokaal,
 * GA_CREDENTIALS_BASE64 op Railway). Het ondertekenen en opvragen doet de
 * officiële bibliotheek van Google (google/auth), die al in het project zit.
 *
 * Dit is een aparte kleine class, zodat tests hem kunnen vervangen door een
 * nepversie en er in tests nooit een echt verzoek naar Google gaat.
 */
class GoogleAccessToken
{
    /**
     * $scope zegt waarvoor de code geldt, bijvoorbeeld Google Ads.
     */
    public function forScope(string $scope): string
    {
        // Een pad naar het sleutelbestand (lokaal) of de inhoud ervan (Railway)
        $credentials = config('services.google_analytics.credentials');

        if (blank($credentials)) {
            throw new RuntimeException('De sleutel van het robotaccount is niet ingesteld (GA_CREDENTIALS_PATH of GA_CREDENTIALS_BASE64).');
        }

        // Een pad dat vanaf de projectmap is opgegeven (bijv. storage/app/private/...) ook vinden
        if (is_string($credentials) && ! is_file($credentials) && is_file(base_path($credentials))) {
            $credentials = base_path($credentials);
        }

        try {
            $token = (new ServiceAccountCredentials($scope, $credentials))->fetchAuthToken();
        } catch (InvalidArgumentException|LogicException $e) {
            // Meldingen van de bibliotheek zelf over het bestand, bijv. "file does not exist"
            throw new RuntimeException('De sleutel van het robotaccount is niet bruikbaar: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Bewust zonder de oorspronkelijke melding: we willen zeker weten dat er
            // nooit iets van de sleutel in een log terechtkomt
            throw new RuntimeException('Inloggen bij Google met het robotaccount is mislukt (' . class_basename($e) . ').');
        }

        if (empty($token['access_token'])) {
            throw new RuntimeException('Google gaf geen toegangscode terug voor het robotaccount.');
        }

        return (string) $token['access_token'];
    }
}