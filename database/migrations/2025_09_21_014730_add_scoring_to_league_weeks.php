<?php

// database/migrations/2025_09_21_000002_add_scoring_to_league_weeks.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_weeks', function (Blueprint $table) {
            $table->unsignedSmallInteger('ends')->default(10)->after('date');
            $table->unsignedTinyInteger('arrows_per_end')->default(3)->after('ends');
        });
    }

    public function down(): void
    {
        Schema::table('league_weeks', function (Blueprint $table) {
            $table->dropColumn(['ends', 'arrows_per_end']);
        });
    }
};
