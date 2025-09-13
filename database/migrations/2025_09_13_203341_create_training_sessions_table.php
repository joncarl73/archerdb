<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loadout_id')->nullable()->constrained()->nullOnDelete();

            // Meta
            $table->string('title', 120)->nullable();
            $table->dateTime('session_at')->nullable();
            $table->string('location', 120)->nullable();

            // Setup / rules
            $table->unsignedSmallInteger('distance_m')->nullable();        // e.g. 18, 25, 70
            $table->string('round_type', 40)->nullable();                  // practice|wa18|wa25|vegas300|custom
            $table->unsignedTinyInteger('arrows_per_end')->default(3);     // 3 or 6 (or other)
            $table->unsignedTinyInteger('max_score')->default(10);         // 10 or 11

            // Plan / progress
            $table->unsignedSmallInteger('ends_planned')->nullable();
            $table->unsignedSmallInteger('ends_completed')->default(0);

            // Aggregates (denormalized for quick listing)
            $table->unsignedSmallInteger('total_score')->default(0);
            $table->unsignedSmallInteger('x_count')->default(0);

            // Session context
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('rpe')->nullable();                // perceived exertion 1–10
            $table->json('tags')->nullable();
            $table->json('weather')->nullable();

            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'session_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};