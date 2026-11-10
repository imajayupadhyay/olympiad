<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A recovery action that can't run in the session's current state. The message is safe
 * to show to the admin as-is.
 */
class ExamSessionException extends RuntimeException {}
