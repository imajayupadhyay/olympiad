<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the "link_share" qualification mode: the referrer's reward is driven by
 * the act of copying/sharing their own link — no open or signup required. Each
 * share is appended to referral_shares (intentionally NOT de-duped; every action
 * counts). Allowed qualify_on values are enforced in ReferralSettingController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->nullable(); // copy | whatsapp | email | native
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();

            $table->index('referrer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_shares');
    }
};
