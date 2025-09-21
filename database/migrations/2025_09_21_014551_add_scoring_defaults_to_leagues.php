<?php

// database/migrations/2025_09_21_000001_add_scoring_defaults_to_leagues.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedSmallInteger('ends_per_day')->default(10)->after('lane_breakdown');
            $table->unsignedTinyInteger('arrows_per_end')->default(3)->after('ends_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['ends_per_day', 'arrows_per_end']);
        });
    }
};
