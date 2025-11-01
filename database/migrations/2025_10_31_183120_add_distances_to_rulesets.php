<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (! Schema::hasColumn('rulesets', 'distances_m')) {
                $t->json('distances_m')->nullable()->after('x_value'); // array of meters, e.g. [18,50,60]
            }
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (Schema::hasColumn('rulesets', 'distances_m')) {
                $t->dropColumn('distances_m');
            }
        });
    }
};
