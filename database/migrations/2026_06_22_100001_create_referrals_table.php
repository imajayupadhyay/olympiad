<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution + qualification ledger. One row per referred signup.
 * Source of truth for "who referred whom" and the reward lifecycle; the
 * actual discounts are delivered as personal coupons (referrer_reward_coupon_id /
 * referee_welcome_coupon_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('referral_code');                                  // snapshot of the code used
            $table->enum('status', ['pending', 'qualified', 'rewarded'])->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->foreignId('referrer_reward_coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('referee_welcome_coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
