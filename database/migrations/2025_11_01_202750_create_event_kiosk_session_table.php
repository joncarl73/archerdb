<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_kiosk_sessions', function (Blueprint $table) {
            $table->id();

            // Ownership
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('event_line_time_id');

            // Assignment
            $table->json('participants')->nullable(); // [event_participant_id, ...]
            $table->json('lanes')->nullable();        // reserved (structure like [{participant_id, lane, slot}, ...])

            // Access
            $table->string('token', 80)->unique();    // public landing: /k/{token}
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // FKs (no cascade delete by default; change if you prefer)
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('event_line_time_id')->references('id')->on('event_line_times')->onDelete('cascade');

            // Helpful indexes
            $table->index(['event_id', 'event_line_time_id'], 'ekiosk_event_line_idx');
            $table->index(['event_id', 'is_active'], 'ekiosk_event_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_kiosk_sessions');
    }
};
