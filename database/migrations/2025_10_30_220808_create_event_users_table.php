<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_users', function (Blueprint $t) {
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('user_id');
            $t->string('role'); // 'owner' | 'manager' (same idea as leagues)
            $t->timestamps();

            $t->primary(['event_id', 'user_id']);
            $t->index('user_id');

            $t->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_users');
    }
};
