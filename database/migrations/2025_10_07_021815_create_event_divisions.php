<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_divisions')) {
            Schema::create('event_divisions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('event_id')->constrained()->cascadeOnDelete();
                $t->string('name');
                $t->json('rules')->nullable(); // min_age, max_age, gender, bow_class, distance, etc
                $t->unsignedInteger('capacity')->nullable(); // optional overall cap
                $t->timestamps();
                $t->unique(['event_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_divisions');
    }
};
