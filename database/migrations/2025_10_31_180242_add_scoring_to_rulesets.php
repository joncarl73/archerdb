<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            // JSON array of integers (e.g., [1,2,...,10])
            if (! Schema::hasColumn('rulesets', 'scoring_values')) {
                $t->json('scoring_values')->nullable()->after('description');
            }
            // X ring value (e.g., 10 or 11)
            if (! Schema::hasColumn('rulesets', 'x_value')) {
                $t->unsignedSmallInteger('x_value')->nullable()->after('scoring_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (Schema::hasColumn('rulesets', 'x_value')) {
                $t->dropColumn('x_value');
            }
            if (Schema::hasColumn('rulesets', 'scoring_values')) {
                $t->dropColumn('scoring_values');
            }
        });
    }
};
