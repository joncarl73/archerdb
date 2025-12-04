<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_checkins', function (Blueprint $t) {
            if (! Schema::hasColumn('event_checkins', 'checked_in_at')) {
                $t->timestamp('checked_in_at')
                    ->nullable()
                    ->after('lane_slot');
            }

            // Optional: make lane_number/lane_slot consistent with leagues
            if (Schema::hasColumn('event_checkins', 'lane_number')) {
                $t->unsignedSmallInteger('lane_number')->nullable()->change();
            }
            if (Schema::hasColumn('event_checkins', 'lane_slot')) {
                $t->string('lane_slot', 10)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_checkins', function (Blueprint $t) {
            if (Schema::hasColumn('event_checkins', 'checked_in_at')) {
                $t->dropColumn('checked_in_at');
            }
        });
    }
};
