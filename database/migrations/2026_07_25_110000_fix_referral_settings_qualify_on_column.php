<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair `referral_settings.qualify_on`.
 *
 * 2026_06_22_110000 intended to relax this column from an enum to a plain string,
 * but on MySQL it stayed an enum — `enum('registration','first_paid_enrollment',
 * 'link_click')`. The fourth mode, `link_share` (added with referral_shares in
 * 2026_07_02_100000), was therefore only ever valid in PHP: saving it from
 * Admin → Referrals → Settings failed with
 * "SQLSTATE[01000] Data truncated for column 'qualify_on'".
 *
 * Forced with explicit DDL rather than ->change() so it cannot silently remain an
 * enum, and so any future mode needs no migration at all. Allowed values stay
 * enforced in ReferralSettingController validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `referral_settings`
                 MODIFY `qualify_on` VARCHAR(40) NOT NULL DEFAULT 'registration'"
            );

            return;
        }

        Schema::table('referral_settings', function (Blueprint $table) {
            $table->string('qualify_on', 40)->default('registration')->change();
        });
    }

    public function down(): void
    {
        // Deliberately not restoring the enum: narrowing it back would truncate any
        // row already saved as link_share. The string form is the correct shape.
    }
};
