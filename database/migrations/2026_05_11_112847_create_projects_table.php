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
       Schema::create('projects', function (Blueprint $table) {
        $table->id();
        // De koppeling naar de klant:
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        
        $table->string('name');
        $table->string('status')->default('Strategie'); // Bijv: Strategie, Design, Dev, Live
        $table->integer('progress')->default(0); // Percentage 0-100
        $table->date('deadline')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
