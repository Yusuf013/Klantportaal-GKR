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
    Schema::create('ad_accounts', function (Blueprint $table) {
        $table->id();
        // Bij welke klant hoort dit account? Wordt de klant verwijderd, dan gaat de koppeling mee
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        // 'meta' of later 'google_ads'
        $table->string('platform', 20);
        // Het accountnummer, alleen cijfers
        $table->string('account_id', 30);
        // created_at = de datum waarop de koppeling is gemaakt
        $table->timestamps();

        // Hetzelfde account kan nooit aan twee klanten hangen
        $table->unique(['platform', 'account_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_accounts');
    }
};
