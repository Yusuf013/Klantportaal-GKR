<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('appointments', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
        // De klant die de afspraak maakt
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        // Het specifieke project waar de afspraak over gaat (kolom 1 uit het design)
        $table->foreignId('project_id')->constrained()->onDelete('cascade');
        
        // Afspraak details (Linkerkant van de modal)
        $table->string('title'); // Onderwerp
        $table->enum('type', ['telefoon', 'online', 'fysiek']); // Type gesprek
        $table->dateTime('start_time'); // Geselecteerde datum + tijdslot
        $table->dateTime('end_time'); // Automatisch start_time + 1 uur
        
        // Toelichting / Opmerkingen (Rechtsonder in de modal)
        $table->text('description')->nullable(); 
        
        // Status van de aanvraag
        $table->string('status')->default('In afwachting'); // In afwachting, Bevestigd, Geannuleerd
        
        // Alvast voorbereid voor Fase 2
        $table->string('zoom_link')->nullable();
        $table->string('outlook_event_id')->nullable();
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
