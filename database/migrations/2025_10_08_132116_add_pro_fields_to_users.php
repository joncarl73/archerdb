<?php

// database/migrations/2025_10_06_000000_add_pro_fields_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('stripe_customer_id')->nullable()->index();
            $t->string('stripe_subscription_id')->nullable()->index();
            $t->boolean('is_pro')->default(false);
            $t->timestamp('pro_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'is_pro',
                'pro_expires_at',
            ]);
        });
    }
};
