<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('channel', ['email', 'in_app', 'both'])->default('in_app');
            $table->enum('audience', ['all', 'exam', 'class'])->default('all');
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->foreignId('class_level_id')->nullable()->constrained('class_levels')->nullOnDelete();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->enum('status', ['sent', 'partial', 'failed'])->default('sent');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
