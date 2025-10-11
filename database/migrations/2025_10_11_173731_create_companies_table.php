<?php

// database/migrations/2025_10_11_000000_create_companies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Optional: owner user for convenience (not required)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('company_name');
            $table->string('legal_name')->nullable();
            $table->string('website')->nullable();
            $table->string('support_email')->nullable();
            $table->string('phone')->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();

            // Extras
            $table->string('industry')->nullable();
            $table->string('tax_id')->nullable();
            $table->unsignedInteger('company_size')->nullable();
            $table->string('logo_path')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
