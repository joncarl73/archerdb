<?php

use App\Enums\EventKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->uuid('public_uuid')->unique();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->string('title');
            // kind lets us branch behavior gradually while reusing flows
            $t->string('kind'); // see EventKind enum below
            // scoring mode + simple toggles (extensible later)
            $t->string('scoring_mode')->default('personal'); // personal|kiosk|both
            $t->boolean('is_published')->default(false);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['owner_id', 'kind', 'is_published']);
        });

        Schema::table('leagues', function (Blueprint $t) {
            $t->foreignId('event_id')->nullable()->after('id')->constrained('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leagues', fn (Blueprint $t) => $t->dropConstrainedForeignId('event_id'));
        Schema::dropIfExists('events');
    }
};
