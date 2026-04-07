<?php

namespace App\Services\Images;

use App\Exceptions\UnsupportedImageFormatException;

/**
 * Imagick-backed image resizer.
 *
 * Handles the same four raster formats as GD plus HEIC and HEIF when the host
 * Imagick build includes libheif. Reports unsupported when the Imagick PHP
 * extension is not loaded so callers can fall through to another backend.
 */
class ImagickImageResizer implements ImageResizer
{
    /**
     * Mime → Imagick format identifier. Used as the supported-format set and as
     * the encoder lookup; replacing a match expression eliminates the
     * unreachable UnhandledMatchError branch that coverage tooling otherwise flags.
     *
     * @var array<string, string>
     */
    private const array MIME_TO_FORMAT = [
        'image/png' => 'png32',
        'image/jpeg' => 'jpeg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    public function supports(string $mime): bool
    {
        if (! $this->isExtensionLoaded()) {
            return false;
        }

        return isset(self::MIME_TO_FORMAT[$mime]);
    }

    public function resize(string $bytes, string $mime, int $maxEdge): string
    {
        try {
            $im = new \Imagick;
            $im->readImageBlob($bytes);
        } catch (\ImagickException) {
            throw new UnsupportedImageFormatException(__('This file does not appear to be a supported image.'));
        }

        // Flatten animated GIF / multi-frame HEIF to the first frame.
        $im->setIteratorIndex(0);

        $srcW = $im->getImageWidth();
        $srcH = $im->getImageHeight();
        [$dstW, $dstH] = Dimensions::scale($srcW, $srcH, $maxEdge);

        if ($dstW === $srcW && $dstH === $srcH) {
            $im->clear();

            return $bytes;
        }

        $im->resizeImage($dstW, $dstH, \Imagick::FILTER_LANCZOS, 1);
        $im->setImageFormat(self::MIME_TO_FORMAT[$mime]);
        $out = $im->getImageBlob();
        $im->clear();

        return $out;
    }

    /**
     * Runtime check for the Imagick PHP extension. Overridable by tests.
     *
     * @return bool True when the Imagick extension is loaded.
     */
    protected function isExtensionLoaded(): bool
    {
        return extension_loaded('imagick');
    }
}
