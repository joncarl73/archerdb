<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::getDatabaseName();

        // league_checkins: (league_id, week_number)
        if (! $this->hasIndex($db, 'league_checkins', 'lc_league_week_idx', ['league_id', 'week_number'])) {
            Schema::table('league_checkins', function (Blueprint $t) {
                $t->index(['league_id', 'week_number'], 'lc_league_week_idx');
            });
        }

        // league_checkins: (league_id, week_number, lane_number, lane_slot)
        if (! $this->hasIndex($db, 'league_checkins', 'lc_lane_sort_idx', ['league_id', 'week_number', 'lane_number', 'lane_slot'])) {
            Schema::table('league_checkins', function (Blueprint $t) {
                $t->index(['league_id', 'week_number', 'lane_number', 'lane_slot'], 'lc_lane_sort_idx');
            });
        }

        // kiosk_sessions: (league_id, week_number)
        if (! $this->hasIndex($db, 'kiosk_sessions', 'ks_league_week_idx', ['league_id', 'week_number'])) {
            Schema::table('kiosk_sessions', function (Blueprint $t) {
                $t->index(['league_id', 'week_number'], 'ks_league_week_idx');
            });
        }

        // kiosk_sessions: (event_id, event_line_time_id)
        // NOTE: You already have `kiosk_sessions_event_id_event_line_time_id_index` — this will skip if that exists.
        if (! $this->hasIndex($db, 'kiosk_sessions', 'ks_event_time_idx', ['event_id', 'event_line_time_id'])) {
            Schema::table('kiosk_sessions', function (Blueprint $t) {
                $t->index(['event_id', 'event_line_time_id'], 'ks_event_time_idx');
            });
        }

        // league_participants: (league_id, user_id)
        if (! $this->hasIndex($db, 'league_participants', 'lp_league_user_idx', ['league_id', 'user_id'])) {
            Schema::table('league_participants', function (Blueprint $t) {
                $t->index(['league_id', 'user_id'], 'lp_league_user_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop only the indexes we created by name (safe even if missing)
        Schema::table('league_checkins', function (Blueprint $t) {
            $this->safeDropIndex($t, 'lc_league_week_idx');
            $this->safeDropIndex($t, 'lc_lane_sort_idx');
        });

        Schema::table('kiosk_sessions', function (Blueprint $t) {
            $this->safeDropIndex($t, 'ks_league_week_idx');
            // don't drop the existing native index; only drop ours if present
            $this->safeDropIndex($t, 'ks_event_time_idx');
        });

        Schema::table('league_participants', function (Blueprint $t) {
            $this->safeDropIndex($t, 'lp_league_user_idx');
        });
    }

    /**
     * Check if an index exists either by name OR by exact column set (order matters).
     */
    private function hasIndex(string $schema, string $table, string $desiredName, array $desiredColumns): bool
    {
        // 1) by name
        $byName = DB::selectOne(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$schema, $table, $desiredName]
        );
        if ($byName) {
            return true;
        }

        // 2) by exact column set (any index with same columns in same order)
        $rows = DB::select(
            'SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$schema, $table]
        );

        $map = [];
        foreach ($rows as $r) {
            $idx = $r->INDEX_NAME;
            $map[$idx] = $map[$idx] ?? [];
            $map[$idx][] = $r->COLUMN_NAME;
        }

        foreach ($map as $cols) {
            if ($cols === $desiredColumns) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safely drop an index by name if it exists.
     */
    private function safeDropIndex(Blueprint $t, string $indexName): void
    {
        try {
            $t->dropIndex($indexName);
        } catch (\Throwable $e) {
            // ignore if missing
        }
    }
};
