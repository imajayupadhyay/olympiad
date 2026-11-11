<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hall tickets: the profile documents a student submits for verification and the
 * one-per-student verification request an admin approves (§9.7, §12.14).
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Private-disk paths (never public URLs); served through authorised routes.
            $table->string('live_photo')->nullable()->after('photo');
            $table->string('passport_photo')->nullable()->after('live_photo');
            $table->string('school_id_card')->nullable()->after('passport_photo');
            $table->string('coordinator_name', 100)->nullable()->after('school_address');
            $table->string('coordinator_phone', 25)->nullable()->after('coordinator_name');
        });

        Schema::create('hall_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('roll_number', 20)->nullable()->unique();   // minted on first approval, never changes
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('submission_count')->default(1);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_tickets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['live_photo', 'passport_photo', 'school_id_card', 'coordinator_name', 'coordinator_phone']);
        });
    }
};
