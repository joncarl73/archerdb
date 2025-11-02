<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            // New fields (company-defined defaults)
            $table->unsignedSmallInteger('ends_per_session')->default(10)->after('description');
            $table->unsignedTinyInteger('arrows_per_end')->default(3)->after('ends_per_session');
            // Use varchar to allow AB / ABCD / ABCDEF (A/B, A/B/C/D, etc.)
            $table->string('lane_breakdown', 16)->default('single')->after('arrows_per_end');
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            $table->dropColumn(['ends_per_session', 'arrows_per_end', 'lane_breakdown']);
        });
    }
};
