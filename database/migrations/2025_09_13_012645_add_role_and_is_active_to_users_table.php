<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_role_and_is_active_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('standard')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->index(['role', 'is_active']);
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn(['role','is_active']);
        });
    }
};
