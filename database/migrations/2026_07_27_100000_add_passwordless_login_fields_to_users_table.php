<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            // Deliberately non-unique: one parent WhatsApp number may belong to
            // more than one child account. The login flow handles that safely
            // with an account chooser after possession has been proven.
            $table->string('phone_e164', 16)->nullable()->after('phone')->index();
            $table->timestamp('phone_verified_at')->nullable()->after('phone_e164');
        });

        // Best-effort backfill for the Indian numbers accepted by the existing
        // registration forms. Unrecognised legacy values remain available in
        // `phone`, but cannot be used as a login identity until corrected.
        DB::table('users')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $digits = preg_replace('/\D+/', '', (string) $user->phone);

                    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
                        $digits = substr($digits, 1);
                    }

                    if (strlen($digits) === 10 && preg_match('/^[6-9]\d{9}$/', $digits)) {
                        $digits = '91'.$digits;
                    }

                    if (strlen($digits) === 12 && preg_match('/^91[6-9]\d{9}$/', $digits)) {
                        DB::table('users')->where('id', $user->id)->update([
                            'phone_e164' => '+'.$digits,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verified_at']);
            $table->string('phone', 15)->nullable()->change();
        });
    }
};
