<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->unsignedSmallInteger('week_number'); // 1..N
            $table->date('date'); // computed event date
            $table->boolean('is_canceled')->default(false);
            $table->timestamps();
            $table->unique(['league_id', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_weeks');
    }
};
