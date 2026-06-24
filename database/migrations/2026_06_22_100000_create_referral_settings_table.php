<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row configuration for the referral program (admin-managed).
 * Always accessed via App\Models\ReferralSetting::current().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);

            // Referee (new student) welcome discount.
            $table->enum('referee_discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('referee_discount_value', 10, 2)->default(0);
            $table->decimal('referee_max_discount', 10, 2)->nullable();        // cap for percentage
            $table->decimal('referee_min_order_amount', 10, 2)->default(0);

            // Referrer (sharer) reward.
            $table->enum('referrer_reward_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('referrer_reward_value', 10, 2)->default(0);
            $table->decimal('referrer_max_discount', 10, 2)->nullable();       // cap for percentage

            // How many successful referrals unlock a referrer reward.
            $table->unsignedInteger('unlock_threshold')->default(1);

            // When a referral counts as "successful" (switchable hook).
            $table->enum('qualify_on', ['registration', 'first_paid_enrollment'])->default('registration');

            // Validity of minted reward/welcome coupons in days (null = no expiry).
            $table->unsignedInteger('reward_validity_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
