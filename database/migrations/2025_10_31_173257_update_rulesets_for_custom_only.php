<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (! Schema::hasColumn('rulesets', 'company_id')) {
                $t->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (Schema::hasColumn('rulesets', 'is_system')) {
                $t->dropColumn('is_system');
            }
        });

        // If canned rows exist (company_id null), you can either delete them or map them to ArcherDB.
        // Example: delete all canned
        DB::table('rulesets')->whereNull('company_id')->delete();
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $t) {
            if (! Schema::hasColumn('rulesets', 'is_system')) {
                $t->boolean('is_system')->default(false);
            }
            // company_id stays
        });
    }
};
