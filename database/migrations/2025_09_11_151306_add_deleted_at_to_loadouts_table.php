<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loadouts', function (Blueprint $table) {
            // adds nullable `deleted_at` TIMESTAMP
            $table->softDeletes();

            // optional but recommended on busy tables:
            // $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('loadouts', function (Blueprint $table) {
            $table->dropSoftDeletes(); // drops `deleted_at`
        });
    }
};
