<?php

namespace App\Services\Images;

use App\Exceptions\UnsupportedImageFormatException;

/**
 * GD-backed image resizer.
 *
 * Handles the four raster formats GD ships with: PNG, JPEG, WebP, and GIF.
 * GD is bundled with PHP and required by the Laravel runtime, so availability
 * is not checked.
 */
class GdImageResizer implements ImageResizer
{
    /**
     * Set of MIME types whose output format preserves an alpha channel and so
     * needs the destination canvas pre-filled with transparency before resampling.
     */
    private const array ALPHA_MIMES = [
        'image/png' => true,
        'image/webp' => true,
        'image/gif' => true,
    ];

    /**
     * Mime → encoder callable. The lookup replaces a match expression so that
     * coverage tooling does not see an unreachable UnhandledMatchError arm.
     *
     * @var array<string, callable(\GdImage): void>
     */
    private const array ENCODERS = [
        'image/png' => [self::class, 'encodePng'],
        'image/jpeg' => [self::class, 'encodeJpeg'],
        'image/webp' => [self::class, 'encodeWebp'],
        'image/gif' => [self::class, 'encodeGif'],
    ];

    public function supports(string $mime): bool
    {
        return isset(self::ENCODERS[$mime]);
    }

    public function resize(string $bytes, string $mime, int $maxEdge): string
    {
        $src = @imagecreatefromstring($bytes);

        if ($src === false) {
            throw new UnsupportedImageFormatException(__('This file does not appear to be a supported image.'));
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        [$dstW, $dstH] = Dimensions::scale($srcW, $srcH, $maxEdge);

        if ($dstW === $srcW && $dstH === $srcH) {
            return $bytes;
        }

        $dst = imagecreatetruecolor($dstW, $dstH);

        if (isset(self::ALPHA_MIMES[$mime])) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        (self::ENCODERS[$mime])($dst);

        return (string) ob_get_clean();
    }

    private static function encodePng(\GdImage $dst): void
    {
        imagepng($dst, null, 6);
    }

    private static function encodeJpeg(\GdImage $dst): void
    {
        imagejpeg($dst, null, 90);
    }

    private static function encodeWebp(\GdImage $dst): void
    {
        imagewebp($dst, null, 90);
    }

    private static function encodeGif(\GdImage $dst): void
    {
        imagegif($dst);
    }
}
