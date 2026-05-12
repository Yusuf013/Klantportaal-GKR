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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
        $table->foreignId('project_id')->constrained()->onDelete('cascade');
        $table->string('name'); // Bijv: "Design Voorstel V1"
        $table->string('file_path'); // Locatie op de schijf
        $table->string('status')->default('Verzonden'); // Status uit je workflow
        $table->timestamp('approved_at')->nullable(); // Voor het digitale akkoord
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
