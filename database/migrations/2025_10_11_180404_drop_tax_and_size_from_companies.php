<?php

// database/migrations/2025_10_11_120000_drop_tax_and_size_from_companies.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
            if (Schema::hasColumn('companies', 'company_size')) {
                $table->dropColumn('company_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_id')->nullable();
            $table->unsignedInteger('company_size')->nullable();
        });
    }
};
