<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ICS-koppeling van de gepubliceerde Outlook-agenda (alleen voor medewerkers).
            // De link wordt versleuteld opgeslagen en is daardoor veel langer dan de
            // link zelf: daarom 'text' en geen 'string'.
            $table->text('outlook_ics_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('outlook_ics_url');
        });
    }
};