<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a link cannot be created; the message is safe to show to the member.
 */
class InvalidLinkException extends RuntimeException {}
