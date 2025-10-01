<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->date('registration_start_date')->nullable()->after('start_date');
            $table->date('registration_end_date')->nullable()->after('registration_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['registration_start_date', 'registration_end_date']);
        });
    }
};
