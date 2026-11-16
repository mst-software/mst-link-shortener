<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an uploaded image is rejected or cannot be stored; the message is safe to show to the operator.
 */
class MediaUploadException extends RuntimeException {}
