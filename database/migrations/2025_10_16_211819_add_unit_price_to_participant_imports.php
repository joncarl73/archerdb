<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            $table->unsignedInteger('unit_price_cents')->default(200)->after('row_count');
            $table->string('currency', 3)->default('usd')->change(); // ensure default
        });
    }

    public function down(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            $table->dropColumn('unit_price_cents');
        });
    }
};
