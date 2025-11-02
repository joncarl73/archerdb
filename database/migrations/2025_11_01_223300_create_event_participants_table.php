<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_participants', function (Blueprint $t) {
            $t->id();

            $t->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Link to a platform user if applicable
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Basic identity
            $t->string('first_name', 100)->nullable();
            $t->string('last_name', 100)->nullable();
            $t->string('email', 255)->nullable();

            // Optional federation fields
            $t->string('membership_id', 64)->nullable();   // e.g., USA Archery/WA number
            $t->string('club', 128)->nullable();

            // Event classification / divisions
            $t->string('division', 64)->nullable();        // e.g., "Senior", "Junior", "Master 50+"
            $t->string('bow_type', 32)->nullable();        // e.g., "Recurve", "Compound", "Barebow"
            $t->string('gender', 16)->nullable();          // e.g., "Male", "Female", "Open"
            $t->boolean('is_para')->default(false);        // para archer?
            $t->boolean('uses_wheelchair')->default(false);
            $t->string('classification', 64)->nullable();  // optional extra label
            $t->string('age_class', 32)->nullable();       // if you want separate age field
            $t->json('meta')->nullable();                  // future-proof catch-all

            $t->timestamps();

            $t->index(['event_id', 'last_name', 'first_name']);
            $t->index(['event_id', 'division', 'bow_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
