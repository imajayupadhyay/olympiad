<?php

namespace App\Services\Notifications;

use App\Services\Notifications\WhatsApp\WhatsAppProviderException;

/**
 * A Bird API error. `status` is the HTTP status (0 = transport failure),
 * `retryAfter` is set for HTTP 429. Same constructor and helpers as before;
 * it now extends the provider-neutral WhatsAppProviderException.
 */
class BirdException extends WhatsAppProviderException {}
