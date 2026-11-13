<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use RuntimeException;

/** The same message was already sent to the same audience within the guard window. */
class DuplicateBroadcastException extends RuntimeException
{
    public function __construct(public readonly NotificationLog $existing)
    {
        parent::__construct('An identical broadcast was sent '.$existing->created_at?->diffForHumans().'.');
    }
}
