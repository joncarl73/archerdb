<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loadouts', function (Blueprint $table) {
            // requires doctrine/dbal to `change()` columns:
            // composer require doctrine/dbal
            $table->string('bow_type', 20)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loadouts', function (Blueprint $table) {
            // rollback to a default if you had one (optional)
            // $table->string('bow_type', 20)->nullable()->default('recurve')->change();
        });
    }
};

