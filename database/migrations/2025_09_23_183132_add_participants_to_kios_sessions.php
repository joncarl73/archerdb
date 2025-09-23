<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_participants_to_kiosk_sessions.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_sessions', function (Blueprint $table) {
            $table->json('participants')->nullable()->after('week_number');
            // keep 'lanes' for backward compatibility; can be dropped later
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_sessions', function (Blueprint $table) {
            $table->dropColumn('participants');
        });
    }
};
