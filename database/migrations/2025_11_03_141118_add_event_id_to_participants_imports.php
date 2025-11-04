<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->cascadeOnDelete();

            // Optional safety: prevent both being set at once
            $table->index(['league_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
