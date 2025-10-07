<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_infos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->string('title')->nullable();
            $t->string('registration_url')->nullable();
            $t->string('banner_path')->nullable();
            $t->longText('content_html')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();
        });

        Schema::table('league_infos', function (Blueprint $t) {
            $t->foreignId('event_id')->nullable()->after('league_id')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('league_infos', fn (Blueprint $t) => $t->dropConstrainedForeignId('event_id'));
        Schema::dropIfExists('event_infos');
    }
};
