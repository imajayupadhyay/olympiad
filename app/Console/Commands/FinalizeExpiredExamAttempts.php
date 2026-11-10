<?php

namespace App\Console\Commands;

use App\Services\ExamSessionService;
use Illuminate\Console\Command;

class FinalizeExpiredExamAttempts extends Command
{
    protected $signature = 'exam-attempts:finalize-expired';

    protected $description = 'Score open exam attempts whose deadline has passed (skips paused sessions).';

    public function handle(ExamSessionService $sessions): int
    {
        $count = $sessions->sweepExpired();
        $this->info("Finalised {$count} expired attempt(s).");

        return self::SUCCESS;
    }
}
