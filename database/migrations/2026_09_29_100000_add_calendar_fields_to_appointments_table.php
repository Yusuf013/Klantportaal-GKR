<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outlook-koppeling (FR-08, ADR-011): locatie, reistijd, organisator en de sync-status.
 *
 * Alles nullable of met default, zodat bestaande afspraken ongewijzigd blijven werken.
 * `outlook_event_id` bestond al (voorbereid in de oorspronkelijke migratie) en wordt nu het
 * Outlook-event in de agenda van de organisator. `zoom_link` blijft staan maar wordt niet meer
 * gebruikt; de Teams-link komt in `online_meeting_url`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Alleen bij type 'fysiek': 'bij_gkr' of 'op_locatie'.
            $table->string('location')->nullable()->after('type');
            // Alleen bij 'op_locatie': wordt vóór en na de afspraak als bezet geblokkeerd.
            $table->unsignedSmallInteger('travel_minutes')->nullable()->after('location');

            // De GKR-medewerker vanaf wiens werkmail de uitnodiging wordt verstuurd.
            $table->foreignId('organizer_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();

            $table->string('online_meeting_url', 1024)->nullable()->after('zoom_link');
            // Gedeeld id van het event over alle agenda's heen; nodig om de kopie in de
            // overzichtsagenda (info@) terug te vinden.
            $table->string('ical_uid', 512)->nullable()->after('outlook_event_id');

            // not_required | pending | synced | failed
            $table->string('calendar_sync_status')->default('not_required')->after('ical_uid');
            $table->timestamp('calendar_synced_at')->nullable()->after('calendar_sync_status');
            // Korte, algemene foutomschrijving; nooit een vendor-payload of persoonsgegevens.
            $table->string('calendar_sync_error')->nullable()->after('calendar_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_user_id');
            $table->dropColumn([
                'location', 'travel_minutes', 'online_meeting_url', 'ical_uid',
                'calendar_sync_status', 'calendar_synced_at', 'calendar_sync_error',
            ]);
        });
    }
};
