<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rulesets', function (Blueprint $t) {
            $t->id();
            $t->string('org')->nullable();           // 'WA','USAA','AGB','NFAA','ASA','IBO','IFAA','CUSTOM'
            $t->string('name');                       // e.g., "World Archery Target (Indoor/Outdoor)"
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->boolean('is_system')->default(false); // canned vs user-editable
            $t->json('schema');                       // normalized spec (see JSON schema below)
            $t->timestamps();
        });

        Schema::table('events', function (Blueprint $t) {
            $t->foreignId('ruleset_id')->nullable()->constrained('rulesets')->nullOnDelete();
        });

        Schema::create('event_ruleset_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->json('overrides');                    // partial tree to overlay on ruleset.schema
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ruleset_overrides');
        Schema::table('events', fn (Blueprint $t) => $t->dropConstrainedForeignId('ruleset_id'));
        Schema::dropIfExists('rulesets');
    }
};
