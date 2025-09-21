<?php

// database/migrations/2025_09_21_000100_create_league_checkins_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_checkins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('league_id');
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('participant_name', 160);
            $table->string('participant_email', 190)->nullable();
            $table->unsignedSmallInteger('week_number');
            $table->unsignedSmallInteger('lane_number');
            // Option A:
            $table->string('lane_slot', 10)->default('single');
            // Option B (alternative):
            // $table->enum('lane_slot', ['single', 'A', 'B', 'C', 'D'])->default('single');

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            // (Optional) add FKs if you have the tables:
            $table->foreign('league_id')->references('id')->on('leagues')->cascadeOnDelete();
            $table->foreign('participant_id')->references('id')->on('league_participants')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('league_checkins');
    }
};
