<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where did this student / this payment actually come from?
 *
 * Until now a signup from the /marketing campaign page was indistinguishable
 * from one through the normal /register wizard, so the admin panel could not
 * report on campaign performance. These two columns carry that attribution
 * through Students, Payments and the Student Reports module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // website · marketing · admin
            $table->string('registration_source', 20)->nullable()->index()->after('role');
        });

        Schema::table('payments', function (Blueprint $table) {
            // checkout · onboarding · marketing · admin
            $table->string('source', 20)->nullable()->index()->after('gateway');
        });

        // Everything that already exists pre-dates the campaign page.
        DB::table('users')->whereNull('registration_source')->update(['registration_source' => 'website']);

        DB::table('payments')->whereNull('source')->update([
            'source' => DB::raw("CASE WHEN is_manual = 1 THEN 'admin' ELSE 'checkout' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['registration_source']);
            $table->dropColumn('registration_source');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
