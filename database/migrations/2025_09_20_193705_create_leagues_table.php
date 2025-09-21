<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique(); // for public scoring links
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); // corporate account owner

            $table->string('title');
            $table->string('location')->nullable();

            $table->unsignedSmallInteger('length_weeks'); // total number of weeks
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sun ... 6 = Sat
            $table->date('start_date'); // declared start date

            $table->enum('type', ['open', 'closed'])->default('open');

            // publication flags & guardrails
            $table->boolean('is_published')->default(false);
            $table->boolean('is_archived')->default(false);

            // Closed/paid scaffolding (Stripe Connect — to implement later)
            $table->unsignedInteger('price_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_account_id')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
