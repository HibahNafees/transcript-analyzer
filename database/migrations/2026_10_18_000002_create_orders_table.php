<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('analysis_credits'); // what the order buys
            $table->unsignedInteger('amount_cents');
            $table->string('stripe_event_id')->nullable(); // the event that marked it paid
            $table->timestamp('paid_at')->nullable();      // set once by the Stripe webhook, never overwritten
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
