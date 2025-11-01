<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruleset_discipline', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $t->primary(['ruleset_id', 'discipline_id']);
        });

        Schema::create('ruleset_bow_type', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bow_type_id')->constrained()->cascadeOnDelete();
            $t->primary(['ruleset_id', 'bow_type_id']);
        });

        Schema::create('ruleset_target_face', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('target_face_id')->constrained()->cascadeOnDelete();
            $t->primary(['ruleset_id', 'target_face_id']);
        });

        Schema::create('ruleset_division', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('division_id')->constrained()->cascadeOnDelete();
            $t->primary(['ruleset_id', 'division_id']);
        });

        Schema::create('ruleset_class', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_id')->constrained()->cascadeOnDelete();
            $t->primary(['ruleset_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruleset_class');
        Schema::dropIfExists('ruleset_division');
        Schema::dropIfExists('ruleset_target_face');
        Schema::dropIfExists('ruleset_bow_type');
        Schema::dropIfExists('ruleset_discipline');
    }
};
