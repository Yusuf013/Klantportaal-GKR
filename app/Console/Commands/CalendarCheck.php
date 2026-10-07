<?php

namespace App\Console\Commands;

use App\Contracts\CalendarProvider;
use App\Models\User;
use App\Services\Calendar\CalendarRejected;
use App\Services\Calendar\CalendarTemporarilyUnavailable;
use Illuminate\Console\Command;

/**
 * Handmatige controle van de Outlook-koppeling (ADR-011): kan de app bij de agenda van elke
 * medewerker en bij de overzichtsagenda? Bedoeld voor na het instellen in Entra of het
 * vernieuwen van het client-secret; niet voor CI (roept echte agenda's aan).
 */
class CalendarCheck extends Command
{
    protected $signature = 'calendar:check';

    protected $description = 'Controleer of de Outlook-koppeling bij alle medewerkersagenda\'s kan';

    public function handle(CalendarProvider $calendar): int
    {
        $this->line('Driver: '.config('calendar.driver'));

        $emails = User::query()->where('is_admin', true)->orderBy('email')->pluck('email')->all();
        $overview = config('calendar.overview_mailbox');

        if ($overview) {
            $emails[] = $overview;
        }

        if ($emails === []) {
            $this->warn('Er zijn geen GKR-medewerkers (admins) om te controleren.');

            return self::SUCCESS;
        }

        try {
            $results = $calendar->checkMailboxes($emails);
        } catch (CalendarRejected|CalendarTemporarilyUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($results as $email => $error) {
            if ($error === null) {
                $this->info("OK    {$email}");
            } else {
                $failed++;
                $this->error("FOUT  {$email}: {$error}");
            }
        }

        if ($failed > 0) {
            $this->newLine();
            $this->line('Controleer of het e-mailadres in het platform gelijk is aan het Microsoft 365-adres, en of de mailbox in de groep "Klantportaal-agendas" zit.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
