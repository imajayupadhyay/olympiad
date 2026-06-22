<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "link_click" qualification mode: the referrer's reward is driven by
 * link opens rather than by the referred student registering/paying. Clicks are
 * tracked (deduped per referrer + IP) in referral_clicks.
 *
 * qualify_on is relaxed from a 2-value enum to a plain string (cross-DB friendly);
 * allowed values are enforced in ReferralSettingController validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->string('qualify_on', 40)->default('registration')->change();
        });

        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            // One counted click per referrer + IP (light de-dupe against refresh spam).
            $table->unique(['referrer_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->enum('qualify_on', ['registration', 'first_paid_enrollment'])->default('registration')->change();
        });
    }
};
