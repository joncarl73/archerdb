<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table) {
                // Division for the event (nullable)
                if (! Schema::hasColumn('event_participants', 'event_division_id')) {
                    $table->unsignedBigInteger('event_division_id')->nullable()->after('event_id');
                }

                // Preferred (requested) line time
                if (! Schema::hasColumn('event_participants', 'preferred_line_time_id')) {
                    $table->unsignedBigInteger('preferred_line_time_id')->nullable()->after('event_division_id');
                }

                // Assigned (staff-selected) line time + lane assignment
                if (! Schema::hasColumn('event_participants', 'assigned_line_time_id')) {
                    $table->unsignedBigInteger('assigned_line_time_id')->nullable()->after('preferred_line_time_id');
                }
                if (! Schema::hasColumn('event_participants', 'assigned_lane_number')) {
                    $table->unsignedInteger('assigned_lane_number')->nullable()->after('assigned_line_time_id');
                }
                if (! Schema::hasColumn('event_participants', 'assigned_lane_slot')) {
                    $table->string('assigned_lane_slot', 8)->nullable()->after('assigned_lane_number');
                }

                if (! Schema::hasColumn('event_participants', 'notes')) {
                    $table->text('notes')->nullable()->after('assigned_lane_slot');
                }

                // (Optional) add FKs if you have line-time & division tables
                // $table->foreign('event_division_id')->references('id')->on('event_divisions')->nullOnDelete();
                // $table->foreign('preferred_line_time_id')->references('id')->on('event_line_times')->nullOnDelete();
                // $table->foreign('assigned_line_time_id')->references('id')->on('event_line_times')->nullOnDelete();

                // helpful index
                $table->index(['event_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_participants')) {
            Schema::table('event_participants', function (Blueprint $table) {
                $cols = [
                    'event_division_id',
                    'preferred_line_time_id',
                    'assigned_line_time_id',
                    'assigned_lane_number',
                    'assigned_lane_slot',
                    'notes',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('event_participants', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
