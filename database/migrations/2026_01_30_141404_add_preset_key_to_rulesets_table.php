<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (! Schema::hasColumn('rulesets', 'preset_key')) {
                $t->string('preset_key')->nullable()->after('org');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (Schema::hasColumn('rulesets', 'preset_key')) {
                $t->dropColumn('preset_key');
            }
        });
    }
};
