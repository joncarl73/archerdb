<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();   // e.g. 'target','indoor','outdoor'
            $t->string('label');           // e.g. 'Target', 'Indoor', 'Outdoor'
            $t->timestamps();
        });

        Schema::create('bow_types', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();   // e.g. 'recurve','compound','barebow','longbow'
            $t->string('label');
            $t->timestamps();
        });

        Schema::create('target_faces', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();   // e.g. '122cm', '80cm', '40cm_vert3'
            $t->string('label');           // human-friendly name
            $t->string('kind')->default('wa_target'); // wa_target, wa_vertical_triple, etc.
            $t->unsignedSmallInteger('diameter_cm')->nullable();
            $t->string('zones')->default('10');       // '10', '6', '5'
            $t->timestamps();
        });

        Schema::create('divisions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();   // e.g. 'adult','junior','masters'
            $t->string('label');
            $t->timestamps();
        });

        Schema::create('classes', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();   // e.g. 'male','female','open'
            $t->string('label');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('target_faces');
        Schema::dropIfExists('bow_types');
        Schema::dropIfExists('disciplines');
    }
};
