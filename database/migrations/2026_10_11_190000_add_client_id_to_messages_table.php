<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Geeft elk chatbericht een gesprek: de klant waar het bij hoort (client_id).
 *
 * Voorheen hing een bericht alleen aan een afzender en een ontvanger. Een bericht
 * van een klant ging naar "de eerste admin". Werd dat ene account verwijderd, dan
 * verdwenen de berichten van alle klanten mee. Nu hoort een bericht bij de klant,
 * en heeft een bericht van een klant geen vaste ontvanger meer (GKR leest als team).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Bij welke klant hoort dit bericht? Eén gesprek = één klant met GKR.
            $table->unsignedBigInteger('client_id')->nullable()->index();
        });

        Schema::table('messages', function (Blueprint $table) {
            // Mag leeg zijn: een bericht van een klant is gericht aan GKR als team
            $table->unsignedBigInteger('receiver_id')->nullable()->change();
            // Mag leeg zijn: het antwoord van een medewerker blijft staan als die vertrekt
            $table->unsignedBigInteger('sender_id')->nullable()->change();
        });

        $this->backfill();
    }

    /**
     * Zet bestaande berichten om naar de nieuwe opzet.
     * Public, zodat een test dit ook los kan aanroepen.
     */
    public function backfill(): void
    {
        $adminIds = DB::table('users')->where('is_admin', 1)->pluck('id')->all();

        // 1. Verstuurd door een klant: het gesprek hoort bij de afzender
        DB::table('messages')
            ->whereNull('client_id')
            ->whereNotNull('sender_id')
            ->whereNotIn('sender_id', $adminIds)
            ->update(['client_id' => DB::raw('sender_id')]);

        // 2. Verstuurd door GKR aan een klant: het gesprek hoort bij de ontvanger
        DB::table('messages')
            ->whereNull('client_id')
            ->whereIn('sender_id', $adminIds)
            ->whereNotNull('receiver_id')
            ->whereNotIn('receiver_id', $adminIds)
            ->update(['client_id' => DB::raw('receiver_id')]);

        // 3. Klant aan GKR: geen vaste ontvanger meer. Zo hangt het bericht niet
        //    langer aan het account van één medewerker.
        DB::table('messages')
            ->whereColumn('client_id', 'sender_id')
            ->whereIn('receiver_id', $adminIds)
            ->update(['receiver_id' => null]);
    }

    public function down(): void
    {
        // De lege ontvangers zetten we niet terug: wie de ontvanger was, is niet meer bekend.
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['client_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};