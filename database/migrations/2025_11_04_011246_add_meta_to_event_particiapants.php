<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            // MariaDB 11.4 supports JSON; Laravel will map appropriately.
            // Put it after `status` just for readability.
            $table->json('meta')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('participant_imports', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
