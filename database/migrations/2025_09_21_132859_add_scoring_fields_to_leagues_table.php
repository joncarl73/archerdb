<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            // X-ring value: default 10, can be 11
            $table->unsignedTinyInteger('x_ring_value')
                ->default(10)
                ->after('type');

            // Scoring mode: personal_device or tablet
            $table->enum('scoring_mode', ['personal_device', 'tablet'])
                ->default('personal_device')
                ->after('x_ring_value');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['x_ring_value', 'scoring_mode']);
        });
    }
};
