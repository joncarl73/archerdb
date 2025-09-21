<?php

// database/migrations/2025_09_20_000001_add_lanes_to_leagues_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->unsignedSmallInteger('lanes_count')->default(10)->after('is_published');
            // single lane per target, A/B split (2 per lane), or A/B/C/D (4 per lane)
            $table->string('lane_breakdown', 8)->default('single')->after('lanes_count'); // single|ab|abcd
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['lanes_count', 'lane_breakdown']);
        });
    }
};
