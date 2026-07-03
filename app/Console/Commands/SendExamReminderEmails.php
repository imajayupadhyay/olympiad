<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Services\ManagedEmailService;
use Illuminate\Console\Command;

class SendExamReminderEmails extends Command
{
    protected $signature = 'emails:send-exam-reminders {--hours=24 : Send reminders for exams starting within this many hours}';

    protected $description = 'Queue reminder emails for enrolled students with upcoming exams.';

    public function handle(ManagedEmailService $emails): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $from = now();
        $to = now()->addHours($hours);

        $examIds = Exam::where('status', 'published')
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [$from, $to])
            ->pluck('id');

        if ($examIds->isEmpty()) {
            $this->info('No upcoming exams found.');

            return self::SUCCESS;
        }

        $queued = 0;

        ExamEnrollment::with(['user.classLevel', 'exam.classLevel'])
            ->where('status', 'enrolled')
            ->whereIn('exam_id', $examIds)
            ->chunkById(200, function ($enrollments) use ($emails, &$queued) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->user || ! $enrollment->exam) {
                        continue;
                    }

                    $alreadyQueued = EmailLog::where('template_key', 'exam_reminder')
                        ->where('recipient_user_id', $enrollment->user_id)
                        ->where('related_type', Exam::class)
                        ->where('related_id', $enrollment->exam_id)
                        ->exists();

                    if ($alreadyQueued) {
                        continue;
                    }

                    $emails->queue(
                        'exam_reminder',
                        $enrollment->user,
                        $emails->examReminderVariables($enrollment->user, $enrollment->exam),
                        ['related_type' => Exam::class, 'related_id' => $enrollment->exam_id]
                    );

                    $queued++;
                }
            });

        $this->info("Queued {$queued} exam reminder email(s).");

        return self::SUCCESS;
    }
}
