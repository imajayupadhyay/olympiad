<?php

namespace App\Services\Notifications\WhatsApp;

/** An MSG91 API error (status 0 = transport error, 200 = MSG91 answered with hasError). */
class Msg91Exception extends WhatsAppProviderException {}
