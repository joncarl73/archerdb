<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who uploaded
            $table->string('file_path');            // storage path (private)
            $table->string('original_name');        // original filename
            $table->unsignedInteger('row_count');   // rows detected (participants to add)
            $table->unsignedBigInteger('amount_cents'); // row_count * 200
            $table->string('currency', 10)->default('usd');

            // payment + processing state
            $table->enum('status', [
                'pending_payment', 'paid', 'processing', 'completed', 'canceled', 'failed',
            ])->default('pending_payment');

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // reuse your orders table
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->text('error_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_imports');
    }
};
