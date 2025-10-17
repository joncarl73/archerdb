<?php

// database/migrations/2025_10_14_000001_add_flat_fee_columns.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->integer('default_platform_fee_cents')->nullable()->after('default_platform_fee_bps');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('platform_fee_cents')->nullable()->after('platform_fee_bps');
        });

        // Backfill: convert bps -> cents using current price (best-effort).
        // If your schema has price on products (it does), we can derive:
        DB::statement('
            UPDATE products
            SET platform_fee_cents = FLOOR((COALESCE(platform_fee_bps, 0) * COALESCE(price_cents, 0)) / 10000)
            WHERE platform_fee_cents IS NULL
        ');

        // Sellers default: pick a reasonable default or map from bps using a reference price if you prefer 1:1 swap:
        DB::table('sellers')->whereNull('default_platform_fee_cents')->update([
            'default_platform_fee_cents' => config('payments.default_platform_fee_cents'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('platform_fee_cents');
        });
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('default_platform_fee_cents');
        });
    }
};
