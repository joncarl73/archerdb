<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            // choose a sensible default; 10 is common for indoor lanes
            $table->unsignedInteger('lane_count')->default(10)->after('lane_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            $table->dropColumn('lanes_count');
        });
    }
};
