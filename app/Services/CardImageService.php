<?php

namespace App\Services;

use App\Exceptions\UnsupportedImageFormatException;
use App\Services\Images\ImageResizer;
use Illuminate\Http\UploadedFile;

/**
 * Processes uploaded card images for storage.
 *
 * Storage path: detect MIME from raw bytes (never from the client header), pick
 * the first injected {@see ImageResizer} that supports the detected MIME, resize
 * to a 400px longest edge, and base64-encode for the `cards.image_data` column.
 * The MIME column is set to the detected MIME, which is always one of the
 * whitelisted formats — keeping the existing `CardController::image()` serve path safe.
 */
class CardImageService
{
    private const int MAX_EDGE = 400;

    /**
     * @param  list<ImageResizer>  $resizers  Resizers in preference order; the first that supports the detected MIME is used.
     */
    public function __construct(private readonly array $resizers)
    {
    }

    /**
     * Process an uploaded file into a storable image payload.
     *
     * @param  UploadedFile  $file  The image file as received from the form.
     * @return array{data: string, mime: string} Base64-encoded bytes and the detected MIME.
     *
     * @throws UnsupportedImageFormatException If the file cannot be read or no resizer can process it.
     */
    public function fromUpload(UploadedFile $file): array
    {
        $bytes = @file_get_contents($file->getRealPath());

        if ($bytes === false) {
            throw new UnsupportedImageFormatException(__('The uploaded image could not be read.'));
        }

        return $this->process($bytes);
    }

    /**
     * Detect MIME, hand the bytes to the first supporting resizer, and base64-encode for storage.
     *
     * @param  string  $bytes  The raw image bytes.
     * @return array{data: string, mime: string} Base64-encoded bytes and the detected MIME.
     *
     * @throws UnsupportedImageFormatException If detection fails or no resizer supports the format on this host.
     */
    private function process(string $bytes): array
    {
        $mime = $this->detectMime($bytes);

        if ($mime === null) {
            throw new UnsupportedImageFormatException(__('This file does not appear to be a supported image.'));
        }

        foreach ($this->resizers as $resizer) {
            if ($resizer->supports($mime)) {
                return [
                    'data' => base64_encode($resizer->resize($bytes, $mime, self::MAX_EDGE)),
                    'mime' => $mime,
                ];
            }
        }

        throw new UnsupportedImageFormatException(__('This image format is not supported on this server.'));
    }

    /**
     * Sniff MIME from raw bytes for the six supported formats.
     *
     * @param  string  $bytes  The raw image bytes.
     * @return string|null One of the supported MIME types, or null if unrecognized.
     */
    private function detectMime(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);

        if ($info !== false && ! empty($info['mime'])) {
            $mime = strtolower((string) $info['mime']);
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
                return $mime;
            }
        }

        // ISO BMFF: bytes 4..7 are 'ftyp', bytes 8..11 are the major brand.
        if (strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp') {
            $brand = substr($bytes, 8, 4);
            if (in_array($brand, ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx'], true)) {
                return 'image/heic';
            }
            if (in_array($brand, ['mif1', 'msf1'], true)) {
                return 'image/heif';
            }
        }

        return null;
    }
}
