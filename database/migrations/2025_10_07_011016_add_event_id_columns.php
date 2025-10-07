<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'kiosk_sessions',
            'league_participants',
            'league_weeks',
            'league_week_scores',
            'league_week_ends',
            'league_checkins',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'event_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'kiosk_sessions',
            'league_participants',
            'league_weeks',
            'league_week_scores',
            'league_week_ends',
            'league_checkins',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'event_id')) {
                Schema::table($table, function (Blueprint $t) {
                    // dropConstrainedForeignId is available on recent Laravel
                    $t->dropConstrainedForeignId('event_id');
                });
            }
        }
    }
};
