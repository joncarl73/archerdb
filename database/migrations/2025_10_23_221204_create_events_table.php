<?php

use App\Enums\EventKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Ownership/visibility
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('public_uuid')->unique(); // public landing like leagues

            // Basics
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('kind'); // EventKind string cast on model
            $table->date('starts_on');
            $table->date('ends_on');

            // Publishing
            $table->boolean('is_published')->default(false);

            // Scoring-mode for parity with leagues (stub for later)
            $table->string('scoring_mode')->default('personal'); // personal|kiosk|tablet (future)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
