<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_marketplace_core.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who receives payouts
        Schema::create('sellers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); // organizer account owner
            $t->string('name');
            $t->string('stripe_account_id')->nullable()->index();     // Connect acct
            $t->unsignedInteger('default_platform_fee_bps')->default(500); // 5.00%
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // A sellable listing that points to any domain model (league, event, class, membership…)
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $t->morphs('productable'); // productable_type, productable_id
            $t->string('name');
            $t->string('currency', 3)->default('USD');
            $t->unsignedInteger('price_cents');                 // current price
            $t->unsignedInteger('platform_fee_bps')->nullable(); // overrides seller default if set
            $t->enum('settlement_mode', ['open', 'closed'])->default('open'); // “closed” = we split via Connect
            $t->json('metadata')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // One-seller-per-order
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $t->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('buyer_email')->nullable();
            $t->string('currency', 3);
            $t->unsignedInteger('subtotal_cents');
            $t->unsignedInteger('application_fee_cents')->default(0); // our commission per order
            $t->unsignedInteger('total_cents');                       // subtotal + tax/shipping if ever added
            $t->enum('status', ['draft', 'requires_payment', 'paid', 'failed', 'refunded'])->default('draft');
            // Stripe fields
            $t->string('stripe_checkout_session_id')->nullable()->index();
            $t->string('stripe_payment_intent_id')->nullable()->index();
            $t->string('stripe_charge_id')->nullable()->index();
            $t->string('stripe_transfer_id')->nullable()->index(); // destination transfer created by Stripe for dest charge
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('unit_price_cents');
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedInteger('line_total_cents'); // unit * qty
            $t->json('metadata')->nullable(); // e.g., league_week, class_session_id
            $t->timestamps();
        });

        // Optional: track refunds
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount_cents');
            $t->string('stripe_refund_id')->nullable()->index();
            $t->boolean('application_fee_refunded')->default(false);
            $t->boolean('transfer_reversed')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sellers');
    }
};
