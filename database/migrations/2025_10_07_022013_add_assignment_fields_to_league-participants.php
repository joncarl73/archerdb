<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_participants', function (Blueprint $t) {
            if (! Schema::hasColumn('league_participants', 'event_division_id')) {
                $t->foreignId('event_division_id')->nullable()->after('event_id')->constrained('event_divisions')->nullOnDelete();
            }
            if (! Schema::hasColumn('league_participants', 'preferred_line_time_id')) {
                $t->foreignId('preferred_line_time_id')->nullable()->after('event_division_id')->constrained('event_line_times')->nullOnDelete();
            }
            if (! Schema::hasColumn('league_participants', 'assigned_line_time_id')) {
                $t->foreignId('assigned_line_time_id')->nullable()->after('preferred_line_time_id')->constrained('event_line_times')->nullOnDelete();
            }
            if (! Schema::hasColumn('league_participants', 'assigned_lane_number')) {
                $t->unsignedInteger('assigned_lane_number')->nullable()->after('assigned_line_time_id');
            }
            if (! Schema::hasColumn('league_participants', 'assigned_lane_slot')) {
                $t->string('assigned_lane_slot', 4)->nullable()->after('assigned_lane_number');
            }
            if (! Schema::hasColumn('league_participants', 'assignment_status')) {
                $t->string('assignment_status', 20)->default('pending')->after('assigned_lane_slot'); // pending|assigned|waitlist
            }
        });
    }

    public function down(): void
    {
        Schema::table('league_participants', function (Blueprint $t) {
            foreach ([
                'event_division_id', 'preferred_line_time_id', 'assigned_line_time_id',
                'assigned_lane_number', 'assigned_lane_slot', 'assignment_status',
            ] as $col) {
                if (Schema::hasColumn('league_participants', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
