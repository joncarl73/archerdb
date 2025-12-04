<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Heads-up: assumes you already have events, event_participants, event_line_times tables.
        Schema::create('event_scores', function (Blueprint $table) {
            $table->id();

            // Which event this score belongs to
            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            // Which flight / line this score belongs to (similar to league_week_id)
            $table->foreignId('event_line_time_id')
                ->constrained()
                ->cascadeOnDelete();

            // Which participant this score belongs to
            $table->foreignId('event_participant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Scoring config snapshot (copied from ruleset at scoring start time)
            $table->unsignedTinyInteger('arrows_per_end')->default(3);
            $table->unsignedTinyInteger('ends_planned')->default(10);

            // Label for the scoring system (e.g. "10", "ASA", "NFAA_5", etc.)
            $table->string('scoring_system', 32)->default('10');

            // Ordered list of allowed scoring values for this event (largest → smallest),
            // e.g. [14,12,10,8,5,0] for ASA or [10,9,8,...,0] for WA.
            $table->json('scoring_values')->nullable();

            // X / 12 / 14 value, if applicable
            $table->unsignedSmallInteger('x_value')->nullable();

            // Convenience: top-of-scale value (usually first element of scoring_values)
            $table->unsignedTinyInteger('max_score')->default(10);

            // Totals (denormalized for quick display)
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('x_count')->default(0);

            $table->timestamps();

            // One score row per participant per line time
            $table->unique(
                ['event_line_time_id', 'event_participant_id'],
                'uniq_event_line_participant'
            );
        });

        Schema::create('event_score_ends', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_score_id')
                ->constrained('event_scores')
                ->cascadeOnDelete();

            // 1..N for each end in the session
            $table->unsignedSmallInteger('end_number');

            // Arrow values for this end, in order.
            // We'll use the same structure as league_week_ends.scores: [int|null, ...]
            $table->json('scores')->nullable();

            $table->unsignedInteger('end_score')->default(0);
            $table->unsignedInteger('x_count')->default(0);

            $table->timestamps();

            $table->unique(
                ['event_score_id', 'end_number'],
                'uniq_event_score_end'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_score_ends');
        Schema::dropIfExists('event_scores');
    }
};
