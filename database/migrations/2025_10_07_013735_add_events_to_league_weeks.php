<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) league_weeks: event_id + label + unique for (event_id, week_number)
        Schema::table('league_weeks', function (Blueprint $t) {
            if (! Schema::hasColumn('league_weeks', 'event_id')) {
                $t->foreignId('event_id')->nullable()->after('league_id')->constrained('events')->nullOnDelete();
            }
            if (! Schema::hasColumn('league_weeks', 'label')) {
                $t->string('label')->nullable()->after('week_number');
            }
        });

        // Add unique composite if not already there (wrap in try/catch to avoid re-adding)
        try {
            Schema::table('league_weeks', function (Blueprint $t) {
                $t->unique(['event_id', 'week_number'], 'league_weeks_event_period_unique');
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        Schema::table('league_weeks', function (Blueprint $t) {
            if (Schema::hasColumn('league_weeks', 'label')) {
                $t->dropColumn('label');
            }
            if (Schema::hasColumn('league_weeks', 'event_id')) {
                $t->dropConstrainedForeignId('event_id');
            }
            // Unique will drop with the column; if not, you can explicitly drop it:
            // $t->dropUnique('league_weeks_event_period_unique');
        });
    }
};
