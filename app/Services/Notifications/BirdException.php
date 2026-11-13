<?php

namespace App\Services\Notifications;

use RuntimeException;

/**
 * A Bird API error. `status` is the HTTP status (0 = transport failure),
 * `retryAfter` is set for HTTP 429.
 */
class BirdException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?int $retryAfter = null,
        public readonly ?string $code_ = null,
    ) {
        parent::__construct($message);
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    /** 5xx, timeouts and 429 are worth retrying; 4xx validation errors are not. */
    public function isTransient(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
