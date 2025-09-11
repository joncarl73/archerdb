<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('loadout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loadout_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // 'bow','arrow','sight','rest','stabilizer','release','string','other'
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('model')->nullable();
            $table->json('specs')->nullable(); // e.g. {"draw_weight": 50, "spine":"500", "length":28}
            $table->unsignedSmallInteger('position')->default(0); // ordering in UI
            $table->timestamps();
            $table->index(['loadout_id','category']);
        });
    }
    public function down(): void { Schema::dropIfExists('loadout_items'); }
};
