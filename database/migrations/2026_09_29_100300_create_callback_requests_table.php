<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belverzoeken (design "Op locatie" -> "Belverzoek indienen").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // de klant
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 32);
            $table->text('note')->nullable();
            $table->string('status')->default('open'); // open | afgehandeld
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_requests');
    }
};
