<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reistijdblokken in de agenda's van de betrokken medewerkers (FR-08, ADR-011).
 *
 * Het hoofd-event staat in `appointments.outlook_event_id`; deze tabel houdt de losse
 * "Reistijd"-events bij, zodat ze bij wijzigen of annuleren weer opgeruimd kunnen worden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // travel_before | travel_after
            $table->string('external_id', 512);
            $table->timestamps();

            $table->unique(['appointment_id', 'user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_calendar_events');
    }
};
