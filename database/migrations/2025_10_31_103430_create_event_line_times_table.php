<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_line_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->date('line_date');                // e.g., 2025-10-31
            $table->time('start_time');               // local time (no tz)
            $table->time('end_time');                 // local time (no tz)
            $table->unsignedSmallInteger('capacity'); // e.g., 48
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('event_id')
                ->references('id')->on('events')
                ->cascadeOnDelete();

            // Prevent accidental duplicates: same event/date/start_time
            $table->unique(['event_id', 'line_date', 'start_time']);
            $table->index(['event_id', 'line_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_line_times');
    }
};
