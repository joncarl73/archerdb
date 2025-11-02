<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            if (! Schema::hasColumn('events', 'lanes_count')) {
                $t->unsignedSmallInteger('lanes_count')->default(10)->after('is_published');
            }
            if (! Schema::hasColumn('events', 'lane_breakdown')) {
                // keep same vocabulary as leagues
                $t->enum('lane_breakdown', ['single', 'double'])->default('single')->after('lanes_count');
            }
            if (! Schema::hasColumn('events', 'ends_per_session')) {
                $t->unsignedSmallInteger('ends_per_session')->default(10)->after('lane_breakdown');
            }
            if (! Schema::hasColumn('events', 'arrows_per_end')) {
                $t->unsignedTinyInteger('arrows_per_end')->default(3)->after('ends_per_session');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $t) {
            if (Schema::hasColumn('events', 'arrows_per_end')) {
                $t->dropColumn('arrows_per_end');
            }
            if (Schema::hasColumn('events', 'ends_per_session')) {
                $t->dropColumn('ends_per_session');
            }
            if (Schema::hasColumn('events', 'lane_breakdown')) {
                $t->dropColumn('lane_breakdown');
            }
            if (Schema::hasColumn('events', 'lanes_count')) {
                $t->dropColumn('lanes_count');
            }
        });
    }
};
