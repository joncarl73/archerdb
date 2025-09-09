<?php
// database/migrations/2025_09_09_000000_create_archer_profiles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('archer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('gender')->nullable(); // male, female, nonbinary, prefer_not_to_say, other
            $table->date('birth_date')->nullable();

            $table->string('handedness')->nullable(); // right, left
            $table->boolean('para_archer')->default(false);
            $table->boolean('uses_wheelchair')->default(false);

            $table->string('club_affiliation')->nullable();
            $table->string('us_archery_number', 30)->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('archer_profiles');
    }
};

