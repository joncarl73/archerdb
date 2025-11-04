<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            // Ensure required columns exist
            if (! Schema::hasColumn('event_participants', 'division_name')) {
                $table->string('division_name', 64)->nullable()->after('email');
            }
            if (! Schema::hasColumn('event_participants', 'line_time_id')) {
                $table->unsignedBigInteger('line_time_id')->nullable()->after('uses_wheelchair');
            }
            if (! Schema::hasColumn('event_participants', 'assigned_lane')) {
                $table->unsignedInteger('assigned_lane')->nullable()->after('line_time_id');
            }
            if (! Schema::hasColumn('event_participants', 'assigned_slot')) {
                $table->string('assigned_slot', 8)->nullable()->after('assigned_lane');
            }

            // Soft remove extraneous columns if they exist (keep data by leaving columns; or drop if you prefer)
            foreach (['event_division_id', 'preferred_line_time_id', 'assigned_line_time_id', 'assigned_lane_number', 'assigned_lane_slot', 'membership_id', 'club', 'gender', 'classification', 'age_class', 'meta'] as $col) {
                if (Schema::hasColumn('event_participants', $col)) {
                    // $table->dropColumn($col); // uncomment if you want to actually drop
                }
            }
        });
    }

    public function down(): void
    {
        // No-op (or recreate dropped columns if you actually drop them)
    }
};
