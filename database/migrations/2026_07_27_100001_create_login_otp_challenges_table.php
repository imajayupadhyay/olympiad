<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['email', 'whatsapp']);
            // A keyed fingerprint supports invalidation and abuse controls
            // without retaining a second plaintext copy of the identifier.
            $table->char('identifier_fingerprint', 64)->index();
            $table->string('masked_identifier');
            $table->json('candidate_user_ids');
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('remember')->default(false);
            $table->enum('delivery_status', ['suppressed', 'queued', 'sent', 'failed'])->default('suppressed');
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('selected_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['identifier_fingerprint', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_otp_challenges');
    }
};
