<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            // 1) Drop indexes that depend on columns we’re removing
            //    (name taken from your schema dump)
            $table->dropIndex('event_participants_event_id_division_bow_type_index');

            // 2) Drop unused/legacy columns
            $drop = [
                'event_division_id',
                'preferred_line_time_id',
                'assigned_line_time_id',
                'assigned_lane_number',
                'assigned_lane_slot',
                'membership_id',
                'club',
                'division',        // legacy free-text, we use division_name now
                'gender',
                'classification',
                'age_class',
                'meta',
            ];

            // Guard against columns that might already be gone in some envs
            foreach ($drop as $col) {
                if (Schema::hasColumn('event_participants', $col)) {
                    $table->dropColumn($col);
                }
            }

            // 3) Add the new composite index that matches filters/search
            $table->index(
                ['event_id', 'division_name', 'bow_type'],
                'event_participants_event_id_division_name_bow_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('event_participants', function (Blueprint $table) {
            // Revert index change
            $table->dropIndex('event_participants_event_id_division_name_bow_type_index');
            $table->index(
                ['event_id', 'division', 'bow_type'],
                'event_participants_event_id_division_bow_type_index'
            );

            // Restore dropped columns with reasonable defaults (nullable to be safe)
            $table->unsignedBigInteger('event_division_id')->nullable()->after('event_id');
            $table->unsignedBigInteger('preferred_line_time_id')->nullable()->after('event_division_id');
            $table->unsignedBigInteger('assigned_line_time_id')->nullable()->after('preferred_line_time_id');

            $table->unsignedInteger('assigned_lane_number')->nullable()->after('assigned_line_time_id');
            $table->string('assigned_lane_slot', 8)->nullable()->after('assigned_lane_number');

            $table->string('membership_id', 64)->nullable()->after('division_name');
            $table->string('club', 128)->nullable()->after('membership_id');
            $table->string('division', 64)->nullable()->after('club'); // legacy free-text

            $table->string('gender', 16)->nullable()->after('bow_type');
            $table->string('classification', 64)->nullable()->after('uses_wheelchair');
            $table->string('age_class', 32)->nullable()->after('classification');

            // JSON meta (use same definition you had)
            $table->longText('meta')->nullable()->after('age_class');
        });
    }
};
