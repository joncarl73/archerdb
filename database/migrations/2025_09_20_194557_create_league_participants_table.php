<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable(); // optional for open leagues
            $table->boolean('checked_in')->default(false); // for QR check-in (later)
            $table->timestamps();

            $table->unique(['league_id', 'email']); // prevents dup by email in same league
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_participants');
    }
};
