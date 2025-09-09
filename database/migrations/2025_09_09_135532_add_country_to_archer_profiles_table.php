<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_country_to_archer_profiles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('archer_profiles', function (Blueprint $table) {
            $table->char('country', 2)->nullable()->after('us_archery_number'); // ISO 3166-1 alpha-2
            $table->index('country');
        });
    }

    public function down(): void {
        Schema::table('archer_profiles', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn('country');
        });
    }
};
