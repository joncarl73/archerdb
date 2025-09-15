<?php
// database/migrations/2025_09_14_000001_add_x_value_to_training_sessions.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('training_sessions', function (Blueprint $table) {
            // store the numeric score an 'X' is worth for this session (10 or 11)
            $table->unsignedTinyInteger('x_value')->default(10)->after('max_score');
        });
    }
    public function down(): void {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropColumn('x_value');
        });
    }
};