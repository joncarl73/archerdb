<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('banner_path')->nullable(); // public disk path (e.g. leagues/{id}/banner.webp)
            $table->longText('content_html')->nullable(); // WYSIWYG HTML
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique('league_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_infos');
    }
};
