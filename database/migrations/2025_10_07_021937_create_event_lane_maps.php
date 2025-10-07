<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_lane_maps')) {
            Schema::create('event_lane_maps', function (Blueprint $t) {
                $t->id();
                $t->foreignId('event_id')->constrained()->cascadeOnDelete();
                $t->foreignId('line_time_id')->nullable()->constrained('event_line_times')->cascadeOnDelete();
                $t->unsignedInteger('lane_number'); // 1..N
                $t->string('slot', 4)->nullable();  // A/B/C/D or null if single position per lane
                $t->unsignedInteger('capacity')->default(1);
                $t->timestamps();
                $t->unique(['event_id', 'line_time_id', 'lane_number', 'slot'], 'elm_unique_lane');
                $t->index(['event_id', 'line_time_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lane_maps');
    }
};
