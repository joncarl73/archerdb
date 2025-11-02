<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Add type like leagues: open|closed (drives payments)
            $table->enum('type', ['open', 'closed'])->default('open')->after('ends_on');

            // Switch lane_breakdown from enum('single','double') -> varchar(8) like leagues
            // We must use raw SQL to change enum -> varchar in MariaDB.
        });

        // lane_breakdown: enum -> varchar
        DB::statement("ALTER TABLE `events` MODIFY `lane_breakdown` VARCHAR(8) NOT NULL DEFAULT 'single'");

        // scoring_mode: varchar('personal') -> enum('personal_device','tablet') default 'personal_device'
        DB::statement("
            ALTER TABLE `events`
            MODIFY `scoring_mode` ENUM('personal_device','tablet') NOT NULL DEFAULT 'personal_device'
        ");

        // Backfill data: map legacy values to new scheme
        DB::statement("UPDATE `events` SET `scoring_mode` = 'personal_device' WHERE `scoring_mode` IN ('personal','personal_device','')");
        DB::statement("UPDATE `events` SET `lane_breakdown` = 'AB' WHERE `lane_breakdown` = 'double'");
    }

    public function down(): void
    {
        // Best-effort rollback
        DB::statement("
            ALTER TABLE `events`
            MODIFY `scoring_mode` VARCHAR(255) NOT NULL DEFAULT 'personal'
        ");
        DB::statement("
            ALTER TABLE `events`
            MODIFY `lane_breakdown` ENUM('single','double') NOT NULL DEFAULT 'single'
        ");

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
