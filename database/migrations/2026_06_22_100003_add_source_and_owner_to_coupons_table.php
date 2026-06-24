<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enables auto-generated *personal* coupons used to deliver referral discounts.
 * `source` distinguishes admin coupons from referral-minted ones; `owner_user_id`,
 * when set, restricts redemption to a single user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->enum('source', ['manual', 'referral_welcome', 'referral_reward'])
                ->default('manual')->after('code');
            $table->foreignId('owner_user_id')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();

            $table->index(['source', 'owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['source', 'owner_user_id']);
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('source');
        });
    }
};
