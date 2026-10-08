<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per medewerker: agendakleur en de keuze "Mijn afspraken" / "Iedereen" (FR-08, ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Outlook-kleurpreset (config/calendar.php). NULL = automatische kleur.
            $table->string('calendar_color', 16)->nullable()->after('is_admin');
            // 'all' | 'mine'. Default 'all', zoals de website nu standaard alles toont.
            $table->string('agenda_scope', 8)->default('all')->after('calendar_color');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['calendar_color', 'agenda_scope']);
        });
    }
};
