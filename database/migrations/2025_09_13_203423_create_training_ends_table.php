<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_ends', function (Blueprint $table) {
            $table->id();

            $table->foreignId('training_session_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('end_number');    // 1..n within session

            // Grid/keypad entries: length must match session.arrows_per_end.
            // Each element null|0..max_score (e.g., 0..10 or 0..11). Use "X" logic in app layer if desired,
            // but store as integers here (optionally treat center-X as max_score for totals and track x_count separately).
            $table->json('scores')->nullable();

            // Denormalized per-end summaries for quick totals
            $table->unsignedSmallInteger('end_score')->default(0);
            $table->unsignedTinyInteger('x_count')->default(0);

            $table->timestamps();

            $table->unique(['training_session_id', 'end_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_ends');
    }
};