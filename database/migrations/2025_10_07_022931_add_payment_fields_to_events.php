<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $t) {
            if (! Schema::hasColumn('events', 'price_cents')) {
                $t->integer('price_cents')->nullable()->after('ends_on');
            }
            if (! Schema::hasColumn('events', 'currency')) {
                $t->string('currency', 3)->nullable()->after('price_cents');
            }
            if (! Schema::hasColumn('events', 'stripe_account_id')) {
                $t->string('stripe_account_id')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('events', 'stripe_product_id')) {
                $t->string('stripe_product_id')->nullable()->after('stripe_account_id');
            }
            if (! Schema::hasColumn('events', 'stripe_price_id')) {
                $t->string('stripe_price_id')->nullable()->after('stripe_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $t) {
            foreach (['price_cents', 'currency', 'stripe_account_id', 'stripe_product_id', 'stripe_price_id'] as $col) {
                if (Schema::hasColumn('events', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
