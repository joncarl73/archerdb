<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            // Company-specific rulesets (NULL => global/canned)
            $table->unsignedBigInteger('company_id')->nullable()->after('id');

            // Indexes
            $table->index('company_id');

            // Optional FK if you want cascading cleanup when a company is deleted
            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->onDelete('cascade');
        });

        // NOTE: You currently have a UNIQUE on `slug`.
        // Keeping it global simplifies things (unique across both canned and custom).
        // If you later want per-company uniqueness instead, drop the single-unique and add:
        // Schema::table('rulesets', fn(Blueprint $t) => $t->dropUnique('rulesets_slug_unique'));
        // Schema::table('rulesets', fn(Blueprint $t) => $t->unique(['company_id','slug']));
        //
        // Caveat: MySQL/MariaDB UNIQUE with NULL allows multiple NULL rows; that would allow duplicate
        // slugs among canned rules (company_id = NULL). If you need canned slugs to be unique too,
        // keep the existing global UNIQUE on `slug` (recommended).
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
