<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A hall-ticket action that can't run in the current state. The message is safe to
 * show to the student or admin as-is.
 */
class HallTicketException extends RuntimeException {}
