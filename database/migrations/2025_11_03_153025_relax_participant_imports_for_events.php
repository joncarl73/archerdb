<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            // Make both nullable so either league OR event can own an import
            $table->unsignedBigInteger('league_id')->nullable()->change();
            $table->unsignedBigInteger('event_id')->nullable()->change();

            // (Optional but recommended on MySQL 8+)
            // Enforce at most one owner at the DB level.
            // Some MySQL installations allow CHECK; if yours doesn't, skip this.
            try {
                DB::statement('ALTER TABLE participant_imports
                    ADD CONSTRAINT chk_import_owner
                    CHECK (
                        (league_id IS NOT NULL AND event_id IS NULL)
                        OR (league_id IS NULL AND event_id IS NOT NULL)
                    )');
            } catch (\Throwable $e) {
                // ignore if CHECK not supported
            }
        });
    }

    public function down(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            // You probably don't want to force NOT NULL again, but if you do:
            // $table->unsignedBigInteger('league_id')->nullable(false)->change();
            // $table->unsignedBigInteger('event_id')->nullable(false)->change();

            // Drop the CHECK if it exists
            try {
                DB::statement('ALTER TABLE participant_imports DROP CONSTRAINT chk_import_owner');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};
