<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name');

            $table->enum('environment', ['indoor', 'outdoor'])->default('indoor');

            // Comma-separated distances as requested (e.g. "9m,18m")
            $table->string('distances')->default('18m');

            $table->unsignedInteger('bales_count')->default(10);

            $table->unsignedInteger('targets_per_bale')->default(4);

            $table->unsignedInteger('lanes_per_bale')->default(2);

            /**
             * lane_slot_groups:
             * JSON array of arrays.
             * Example for 4 targets / 2 lanes:
             * [
             *   ["A","C"],
             *   ["B","D"]
             * ]
             */
            $table->json('lane_slot_groups');

            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranges');
    }
};
