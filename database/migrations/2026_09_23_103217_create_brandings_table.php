<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label branding (FR-02 / NFR-03, ADR-010).
 *
 * Nu één record voor de hele installatie. Alle brandingvelden zijn nullable: NULL betekent
 * "niet geconfigureerd" en valt terug op `config/branding.php` — zo kan een default wijzigen
 * zonder datamigratie, en rendert een lege configuratie altijd een complete app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brandings', function (Blueprint $table) {
            $table->id();

            // Voorbereid op het Klant-model (issue #33, ADR-001). Bewust nog geen foreign key:
            // de tabel `klanten` bestaat nog niet. #33 voegt FK + NOT NULL + unique toe
            // (migratiepad in ADR-010). Nooit vullen vanuit request-input.
            $table->unsignedBigInteger('klant_id')->nullable()->index();

            $table->string('organization_name', 40)->nullable();
            $table->string('primary_color', 7)->nullable(); // '#RRGGBB'
            $table->string('accent_color', 7)->nullable();  // '#RRGGBB'
            $table->string('logo_path')->nullable();         // pad op de branding-disk

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brandings');
    }
};
