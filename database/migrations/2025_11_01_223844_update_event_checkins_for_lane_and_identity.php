<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_checkins', function (Blueprint $t) {
            // Add identity columns (nullable)
            if (! Schema::hasColumn('event_checkins', 'first_name')) {
                $t->string('first_name', 100)->nullable()->after('participant_id');
            }
            if (! Schema::hasColumn('event_checkins', 'last_name')) {
                $t->string('last_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('event_checkins', 'email')) {
                $t->string('email', 255)->nullable()->after('last_name');
            }

            // New lane columns (nullable)
            if (! Schema::hasColumn('event_checkins', 'lane_number')) {
                $t->unsignedSmallInteger('lane_number')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('event_checkins', 'lane_slot')) {
                $t->string('lane_slot', 1)->nullable()->after('lane_number');
            }
        });

        // Backfill from old columns if present
        $hasOldLane = Schema::hasColumn('event_checkins', 'lane');
        $hasOldSlot = Schema::hasColumn('event_checkins', 'slot');
        if ($hasOldLane || $hasOldSlot) {
            DB::statement('
                UPDATE event_checkins
                SET
                    lane_number = IFNULL(lane_number, lane),
                    lane_slot   = IFNULL(lane_slot, UPPER(slot))
            ');
        }

        // Drop old columns after backfill
        Schema::table('event_checkins', function (Blueprint $t) use ($hasOldLane, $hasOldSlot) {
            if ($hasOldLane) {
                $t->dropColumn('lane');
            }
            if ($hasOldSlot) {
                $t->dropColumn('slot');
            }
        });

        // Add helpful index if missing
        if (! $this->indexExists('event_checkins', 'event_checkins_event_line_idx')) {
            Schema::table('event_checkins', function (Blueprint $t) {
                $t->index(['event_id', 'event_line_time_id'], 'event_checkins_event_line_idx');
            });
        }

        // Unique (line_time, lane_number, lane_slot) to prevent double booking
        if (! $this->indexExists('event_checkins', 'ux_line_time_lane_slot')) {
            Schema::table('event_checkins', function (Blueprint $t) {
                $t->unique(['event_line_time_id', 'lane_number', 'lane_slot'], 'ux_line_time_lane_slot');
            });
        }

        // Add FK to event_participants if table exists
        if (Schema::hasTable('event_participants') && Schema::hasColumn('event_checkins', 'participant_id')) {
            // Null out bad refs so FK creation won't fail
            DB::statement('
                UPDATE event_checkins ec
                LEFT JOIN event_participants ep ON ep.id = ec.participant_id
                SET ec.participant_id = NULL
                WHERE ec.participant_id IS NOT NULL AND ep.id IS NULL
            ');

            // Create FK if it doesn't already exist
            if (! $this->foreignKeyExists('event_checkins', 'event_checkins_participant_id_foreign')) {
                Schema::table('event_checkins', function (Blueprint $t) {
                    $t->foreign('participant_id')
                        ->references('id')->on('event_participants')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Drop unique/indexes if they exist
        if ($this->indexExists('event_checkins', 'ux_line_time_lane_slot')) {
            Schema::table('event_checkins', function (Blueprint $t) {
                $t->dropUnique('ux_line_time_lane_slot');
            });
        }
        if ($this->indexExists('event_checkins', 'event_checkins_event_line_idx')) {
            Schema::table('event_checkins', function (Blueprint $t) {
                $t->dropIndex('event_checkins_event_line_idx');
            });
        }

        // Drop FK if present (no Doctrine; use raw SQL)
        if ($this->foreignKeyExists('event_checkins', 'event_checkins_participant_id_foreign')) {
            try {
                DB::statement('ALTER TABLE `event_checkins` DROP FOREIGN KEY `event_checkins_participant_id_foreign`');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Restore old columns and drop new ones
        Schema::table('event_checkins', function (Blueprint $t) {
            if (! Schema::hasColumn('event_checkins', 'lane')) {
                $t->unsignedSmallInteger('lane')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('event_checkins', 'slot')) {
                $t->string('slot', 1)->nullable()->after('lane');
            }

            if (Schema::hasColumn('event_checkins', 'lane_number')) {
                $t->dropColumn('lane_number');
            }
            if (Schema::hasColumn('event_checkins', 'lane_slot')) {
                $t->dropColumn('lane_slot');
            }
            if (Schema::hasColumn('event_checkins', 'first_name')) {
                $t->dropColumn('first_name');
            }
            if (Schema::hasColumn('event_checkins', 'last_name')) {
                $t->dropColumn('last_name');
            }
            if (Schema::hasColumn('event_checkins', 'email')) {
                $t->dropColumn('email');
            }
        });
    }

    /** Check if an index exists using SHOW INDEX (works without Doctrine). */
    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return ! empty($rows);
    }

    /** Check if a FK exists using information_schema (no Doctrine). */
    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table, $fkName]);

        return ! empty($rows);
    }
};
