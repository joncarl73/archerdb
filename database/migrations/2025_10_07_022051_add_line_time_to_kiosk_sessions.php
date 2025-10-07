<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_sessions', function (Blueprint $t) {
            if (! Schema::hasColumn('kiosk_sessions', 'event_line_time_id')) {
                $t->foreignId('event_line_time_id')->nullable()->after('event_id')->constrained('event_line_times')->nullOnDelete();
                $t->index(['event_id', 'event_line_time_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_sessions', function (Blueprint $t) {
            if (Schema::hasColumn('kiosk_sessions', 'event_line_time_id')) {
                $t->dropConstrainedForeignId('event_line_time_id');
            }
        });
    }
};
