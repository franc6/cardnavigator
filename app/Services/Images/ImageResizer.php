<?php

namespace App\Services\Images;

use App\Exceptions\UnsupportedImageFormatException;

/**
 * Contract for a backend that resizes image bytes in place.
 *
 * Implementations advertise which MIME types they can handle on the current host
 * via {@see self::supports()} and perform a longest-edge resize via
 * {@see self::resize()}. Selecting a backend is the caller's responsibility.
 */
interface ImageResizer
{
    /**
     * Whether this resizer can handle the given MIME on this host.
     *
     * Covers both format support and runtime extension availability — a backend
     * whose underlying PHP extension is not loaded must return false.
     *
     * @param  string  $mime  The MIME type to check.
     * @return bool True when this resizer can process the format on this host.
     */
    public function supports(string $mime): bool;

    /**
     * Resize so the longest edge is no larger than $maxEdge.
     *
     * Returns the original bytes unchanged when no resize is needed.
     *
     * @param  string  $bytes  The raw image bytes.
     * @param  string  $mime  One of the MIME types reported as supported.
     * @param  int  $maxEdge  Maximum length (in pixels) of the longest edge.
     * @return string The processed bytes in the same source format.
     *
     * @throws UnsupportedImageFormatException If the backend cannot decode the bytes.
     */
    public function resize(string $bytes, string $mime, int $maxEdge): string;
}
