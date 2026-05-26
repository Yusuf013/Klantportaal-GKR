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
    Schema::create('appointment_user', function (Blueprint $table) {
        $table->id();
        // Link naar de specifieke afspraak
        $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
        // Link naar de GKR-medewerker (User model waar is_admin true is)
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_user');
    }
};
