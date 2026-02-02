<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'range_id')) {
                $table->foreignId('range_id')
                    ->nullable()
                    ->constrained('ranges')
                    ->nullOnDelete();
            }
        });

        Schema::table('leagues', function (Blueprint $table) {
            if (! Schema::hasColumn('leagues', 'range_id')) {
                $table->foreignId('range_id')
                    ->nullable()
                    ->constrained('ranges')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'range_id')) {
                $table->dropConstrainedForeignId('range_id');
            }
        });

        Schema::table('leagues', function (Blueprint $table) {
            if (Schema::hasColumn('leagues', 'range_id')) {
                $table->dropConstrainedForeignId('range_id');
            }
        });
    }
};
