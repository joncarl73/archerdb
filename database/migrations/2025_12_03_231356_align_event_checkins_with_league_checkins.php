<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Add missing columns (if not already added in a previous step)
        Schema::table('event_checkins', function (Blueprint $t) {
            if (! Schema::hasColumn('event_checkins', 'participant_name')) {
                $t->string('participant_name', 160)
                    ->nullable()
                    ->after('participant_id');
            }

            if (! Schema::hasColumn('event_checkins', 'participant_email')) {
                $t->string('participant_email', 190)
                    ->nullable()
                    ->after('participant_name');
            }

            if (! Schema::hasColumn('event_checkins', 'checked_in_at')) {
                $t->timestamp('checked_in_at')
                    ->nullable()
                    ->after('lane_slot');
            }
        });

        // 2) Backfill participant_name / participant_email from event_participants
        //    when we have a roster-linked participant_id.
        DB::table('event_checkins')
            ->join('event_participants', 'event_checkins.participant_id', '=', 'event_participants.id')
            ->whereNull('event_checkins.participant_name')
            ->update([
                'event_checkins.participant_name' => DB::raw(
                    "TRIM(CONCAT(COALESCE(event_participants.first_name, ''), ' ', COALESCE(event_participants.last_name, '')))"
                ),
                'event_checkins.participant_email' => DB::raw('event_participants.email'),
            ]);

        // 3) Backfill from free-form identity on the checkin row itself
        DB::table('event_checkins')
            ->whereNull('participant_name')
            ->where(function ($q) {
                $q->whereNotNull('first_name')
                    ->orWhereNotNull('last_name');
            })
            ->update([
                'participant_name' => DB::raw(
                    "TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))"
                ),
                'participant_email' => DB::raw('email'),
            ]);

        // 4) Backfill checked_in_at from created_at / updated_at if missing
        DB::table('event_checkins')
            ->whereNull('checked_in_at')
            ->update([
                'checked_in_at' => DB::raw('COALESCE(updated_at, created_at, NOW())'),
            ]);

        // 5) Drop the unused JSON meta column
        if (Schema::hasColumn('event_checkins', 'meta')) {
            Schema::table('event_checkins', function (Blueprint $t) {
                $t->dropColumn('meta');
            });
        }
    }

    public function down(): void
    {
        // Put meta back (empty) and drop the new columns
        Schema::table('event_checkins', function (Blueprint $t) {
            if (! Schema::hasColumn('event_checkins', 'meta')) {
                $t->json('meta')->nullable();
            }

            if (Schema::hasColumn('event_checkins', 'checked_in_at')) {
                $t->dropColumn('checked_in_at');
            }

            if (Schema::hasColumn('event_checkins', 'participant_email')) {
                $t->dropColumn('participant_email');
            }

            if (Schema::hasColumn('event_checkins', 'participant_name')) {
                $t->dropColumn('participant_name');
            }
        });
    }
};
