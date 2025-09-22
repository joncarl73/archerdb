<?php

// database/migrations/2025_09_22_000001_create_kiosk_sessions.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('league_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('week_number');          // bind to a specific week
            $t->json('lanes');                           // ["5","5A","6B"] (string codes)
            $t->string('token', 64)->unique();           // public token for tablet URL
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_sessions');
    }
};
