<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Maak body optioneel als dat nog niet zo was
            $table->text('body')->nullable()->change();

            // Voeg de velden alleen toe als ze nog niet bestaan
            if (!Schema::hasColumn('messages', 'file_path')) {
                $table->string('file_path')->nullable()->after('body');
            }
            if (!Schema::hasColumn('messages', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('messages', 'file_size')) {
                $table->integer('file_size')->nullable()->after('file_name');
            }
            if (!Schema::hasColumn('messages', 'file_type')) {
                $table->string('file_type')->nullable()->after('file_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('messages', 'file_path') ? 'file_path' : null,
                Schema::hasColumn('messages', 'file_name') ? 'file_name' : null,
                Schema::hasColumn('messages', 'file_size') ? 'file_size' : null,
                Schema::hasColumn('messages', 'file_type') ? 'file_type' : null,
            ]));
        });
    }
};