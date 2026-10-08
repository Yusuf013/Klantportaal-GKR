<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ClosedDay;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Lokale testgegevens voor afspraken en de Outlook-koppeling (ADR-011).
 * Alleen voor ontwikkeling: `php artisan db:seed --class=AppointmentDemoSeeder`.
 * Wachtwoord van alle accounts: `password`.
 */
class AppointmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $employees = collect([
            ['Owen', 'owen@gkr.nl'],
            ['Noah van Schilt', 'noah@gkr.nl'],
            ['Stijn', 'stijn@gkr.nl'],
            ['York', 'york@gkr.nl'],
            ['Bo', 'bo@gkr.nl'],
        ])->map(function (array $row) use ($password) {
            $user = User::firstOrNew(['email' => $row[1]]);
            $user->forceFill(['name' => $row[0], 'password' => $password, 'is_admin' => true, 'email_verified_at' => now()])->save();

            return $user;
        });

        $client = User::firstOrNew(['email' => 'klant@example.com']);
        $client->forceFill(['name' => 'Keurslager Van Strien', 'password' => $password, 'is_admin' => false, 'email_verified_at' => now()])->save();

        $website = Project::firstOrCreate(['user_id' => $client->id, 'name' => 'Website Redesign 2026'], ['status' => 'Strategie', 'progress' => 20]);
        Project::firstOrCreate(['user_id' => $client->id, 'name' => 'Marketing Automation Setup'], ['status' => 'Strategie', 'progress' => 5]);

        $nextMonday = CarbonImmutable::now(config('app.timezone'))->next('monday')->setTime(10, 0);
        [$owen, $noah, $stijn, $york] = $employees->take(4)->all();

        $proposal = Appointment::create([
            'user_id' => $client->id,
            'organizer_user_id' => $owen->id,
            'project_id' => $website->id,
            'title' => 'Marktentree EMEA',
            'type' => Appointment::TYPE_ONLINE,
            'start_time' => $nextMonday,
            'end_time' => $nextMonday->addHour(),
            'status' => AppointmentStatus::Voorstel->value,
        ]);
        $proposal->attendees()->sync([$owen->id, $york->id]);

        foreach ([$nextMonday, $nextMonday->addDay()->setTime(10, 0), $nextMonday->addDays(3)->setTime(13, 0)] as $start) {
            $proposal->options()->create(['start_time' => $start, 'end_time' => $start->addHour()]);
        }

        $confirmed = Appointment::create([
            'user_id' => $client->id,
            'organizer_user_id' => $noah->id,
            'project_id' => $website->id,
            'title' => 'Kleurpalet Beoordeling',
            'type' => Appointment::TYPE_FYSIEK,
            'location' => Appointment::LOCATION_BIJ_GKR,
            'start_time' => $nextMonday->addDays(2)->setTime(14, 0),
            'end_time' => $nextMonday->addDays(2)->setTime(15, 30),
            'status' => AppointmentStatus::Bevestigd->value,
            'calendar_sync_status' => Appointment::SYNC_SYNCED,
        ]);
        $confirmed->attendees()->sync([$noah->id, $stijn->id]);

        ClosedDay::firstOrCreate(['date' => $nextMonday->addDays(4)->toDateString()], ['reason' => 'Teamdag']);
    }
}
