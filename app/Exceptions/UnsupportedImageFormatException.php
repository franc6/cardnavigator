<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a submitted image cannot be processed on this server.
 *
 * The most common cause is a HEIC/HEIF upload on a host without the Imagick
 * PHP extension installed. Callers should surface the (already-translated)
 * message via a validation-style 422 response.
 */
class UnsupportedImageFormatException extends RuntimeException
{
}
