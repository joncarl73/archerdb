<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'lanes_count')) {
                $table->dropColumn('lanes_count');
            }
            if (Schema::hasColumn('events', 'lane_breakdown')) {
                $table->dropColumn('lane_breakdown');
            }
            if (Schema::hasColumn('events', 'ends_per_session')) {
                $table->dropColumn('ends_per_session');
            }
            if (Schema::hasColumn('events', 'arrows_per_end')) {
                $table->dropColumn('arrows_per_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('lanes_count')->nullable();
            $table->string('lane_breakdown', 16)->nullable();
            $table->unsignedInteger('ends_per_session')->nullable();
            $table->unsignedInteger('arrows_per_end')->nullable();
        });
    }
};
