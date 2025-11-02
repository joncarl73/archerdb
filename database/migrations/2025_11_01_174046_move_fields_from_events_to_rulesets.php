<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Keep lanes_count on events (capacity is venue-specific)
            // Make the three fields nullable (soft-deprecate) or drop them if you prefer.
            if (Schema::hasColumn('events', 'ends_per_session')) {
                $table->unsignedSmallInteger('ends_per_session')->nullable()->change();
            }
            if (Schema::hasColumn('events', 'arrows_per_end')) {
                $table->unsignedTinyInteger('arrows_per_end')->nullable()->change();
            }
            if (Schema::hasColumn('events', 'lane_breakdown')) {
                $table->string('lane_breakdown', 16)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Restore non-null defaults if you ever roll back
            if (Schema::hasColumn('events', 'ends_per_session')) {
                $table->unsignedSmallInteger('ends_per_session')->default(10)->change();
            }
            if (Schema::hasColumn('events', 'arrows_per_end')) {
                $table->unsignedTinyInteger('arrows_per_end')->default(3)->change();
            }
            if (Schema::hasColumn('events', 'lane_breakdown')) {
                $table->string('lane_breakdown', 16)->default('single')->change();
            }
        });
    }
};
