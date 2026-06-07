<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One enrolment per student per exam. Free exams enrol instantly (payment_id null);
     * paid exams link to the covering payment once it succeeds.
     */
    public function up(): void
    {
        Schema::create('exam_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('status', ['pending_payment', 'enrolled', 'cancelled'])->default('enrolled');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_id']);
            $table->index(['exam_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_enrollments');
    }
};
