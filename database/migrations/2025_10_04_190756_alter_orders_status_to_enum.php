<?php

// database/migrations/2025_10_04_000001_alter_orders_status_to_enum.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // choose ONE of these two statements:

        // 1) ENUM with explicit states (nice for constraints)
        DB::statement("
            ALTER TABLE `orders`
            MODIFY `status` ENUM('initiated','paid','canceled','failed','refunded')
            NOT NULL DEFAULT 'initiated'
        ");

        // 2) Or, plain VARCHAR (if you prefer flexibility)
        // DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'initiated'");
    }

    public function down(): void
    {
        // If you know the original type, restore it here. Otherwise:
        DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'initiated'");
    }
};
