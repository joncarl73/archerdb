<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_checkins', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('event_line_time_id')->nullable(); // which line time they chose
            $t->unsignedBigInteger('participant_id')->nullable();      // if you use participants table; else user_id
            $t->unsignedBigInteger('user_id')->nullable();            // fallback: linked user
            $t->unsignedSmallInteger('lane')->nullable();             // lane # chosen
            $t->string('slot', 1)->nullable();                        // A/B/C/D/etc
            $t->json('meta')->nullable();                             // any extras (notes, bow type, etc.)
            $t->timestamps();

            $t->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            $t->foreign('event_line_time_id')->references('id')->on('event_line_times')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_checkins');
    }
};
