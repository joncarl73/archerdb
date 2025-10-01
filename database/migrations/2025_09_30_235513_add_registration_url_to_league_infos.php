<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_infos', function (Blueprint $table) {
            $table->string('registration_url')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('league_infos', function (Blueprint $table) {
            $table->dropColumn('registration_url');
        });
    }
};
