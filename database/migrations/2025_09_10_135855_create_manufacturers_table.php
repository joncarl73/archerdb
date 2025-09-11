<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_manufacturers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('categories')->nullable(); // ["bow","arrow","sight",...]
            $table->string('website')->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('manufacturers'); }
};
