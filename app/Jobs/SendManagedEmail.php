<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\ManagedEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendManagedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $emailLogId,
        public array $rendered,
    ) {
    }

    public function handle(ManagedEmailService $emails): void
    {
        $log = EmailLog::find($this->emailLogId);

        if (! $log || $log->status !== 'queued') {
            return;
        }

        $emails->sendRendered($log, $this->rendered);
    }
}
