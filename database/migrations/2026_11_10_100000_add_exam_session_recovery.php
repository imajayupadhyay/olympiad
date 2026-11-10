<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exam session recovery (Ongoing Exams). Additive only: "paused" is a sub-state of
 * in_progress (suspended_at), so the exam_attempts.status enum is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->timestamp('deadline_at')->nullable()->after('started_at');
            $table->timestamp('suspended_at')->nullable()->after('deadline_at');
            // Paused by "Reschedule – resume later": resumes itself inside the personal window.
            $table->boolean('resume_on_window')->default(false)->after('suspended_at');
            $table->timestamp('last_seen_at')->nullable()->after('resume_on_window');
            $table->foreignId('current_question_id')->nullable()->after('last_seen_at')
                ->constrained('questions')->nullOnDelete();
            $table->unsignedTinyInteger('violation_count')->default(0)->after('current_question_id');
            $table->string('session_token', 40)->nullable()->after('violation_count');
            $table->unsignedInteger('extra_seconds')->default(0)->after('session_token');
            $table->unsignedInteger('paused_seconds')->default(0)->after('extra_seconds');
            $table->unsignedTinyInteger('reopen_count')->default(0)->after('paused_seconds');
        });

        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->timestamp('window_starts_at')->nullable()->after('enrolled_at');
            $table->timestamp('window_ends_at')->nullable()->after('window_starts_at');
        });

        Schema::create('exam_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_attempt_id')->nullable()->constrained('exam_attempts')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'user_id']);
            $table->index(['exam_id', 'type']);
        });

        // Backfill the stored deadline for attempts that are open right now, using the
        // formula the room used before: min(started_at + duration, exam ends_at).
        DB::table('exam_attempts')
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exam_attempts.status', 'in_progress')
            ->whereNull('exam_attempts.deadline_at')
            ->select('exam_attempts.id', 'exam_attempts.started_at', 'exam_attempts.created_at', 'exams.duration_minutes', 'exams.ends_at')
            ->orderBy('exam_attempts.id')
            ->each(function ($row) {
                $start = Carbon::parse($row->started_at ?? $row->created_at);
                $deadline = $start->copy()->addMinutes((int) $row->duration_minutes);
                if ($row->ends_at && Carbon::parse($row->ends_at)->lt($deadline)) {
                    $deadline = Carbon::parse($row->ends_at);
                }
                DB::table('exam_attempts')->where('id', $row->id)->update(['deadline_at' => $deadline]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_events');

        Schema::table('exam_enrollments', function (Blueprint $table) {
            $table->dropColumn(['window_starts_at', 'window_ends_at']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_question_id');
            $table->dropColumn([
                'deadline_at', 'suspended_at', 'resume_on_window', 'last_seen_at', 'violation_count', 'session_token',
                'extra_seconds', 'paused_seconds', 'reopen_count',
            ]);
        });
    }
};
