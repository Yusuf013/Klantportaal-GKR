<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dagen waarop heel GKR dicht is (feestdagen, kerstsluiting). Persoonlijke vakanties staan
 * niet hier maar in Outlook als "Afwezig" (ADR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closed_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closed_days');
    }
};
