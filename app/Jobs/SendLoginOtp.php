<?php

namespace App\Jobs;

use App\Models\LoginOtpChallenge;
use App\Services\AuthenticationOtpDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLoginOtp implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 20, 60];

    public function __construct(
        public int $challengeId,
        public string $channel,
        public string $destination,
        public string $recipientName,
        public string $code,
    ) {}

    public function handle(AuthenticationOtpDeliveryService $delivery): void
    {
        $challenge = LoginOtpChallenge::find($this->challengeId);

        if (! $challenge || ! $challenge->isUsable()) {
            return;
        }

        $delivery->send($this->channel, $this->destination, $this->recipientName, $this->code);
        $challenge->update(['delivery_status' => 'sent']);
    }

    public function failed(?Throwable $exception): void
    {
        LoginOtpChallenge::whereKey($this->challengeId)->update(['delivery_status' => 'failed']);

        // Never log the destination, recipient name, provider response body, or code.
        Log::warning('Login OTP delivery failed.', [
            'challenge_id' => $this->challengeId,
            'channel' => $this->channel,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
