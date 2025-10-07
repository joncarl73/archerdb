<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_line_times')) {
            Schema::create('event_line_times', function (Blueprint $t) {
                $t->id();
                $t->foreignId('event_id')->constrained()->cascadeOnDelete();
                $t->string('label')->nullable();      // "Line A – 9:00 AM"
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->unsignedInteger('capacity')->nullable(); // fallback cap if no lane map
                $t->timestamps();
                $t->index(['event_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_line_times');
    }
};
