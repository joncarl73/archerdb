<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            if (Schema::hasColumn('rulesets', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rulesets', function (Blueprint $table) {
            // Restore as nullable (since old unique data may be gone)
            $table->string('slug')->nullable()->after('name');
        });
    }
};
