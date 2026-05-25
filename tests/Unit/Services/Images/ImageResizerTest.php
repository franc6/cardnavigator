<?php

namespace Tests\Unit\Services\Images;

use App\Exceptions\UnsupportedImageFormatException;
use App\Services\Images\GdImageResizer;
use App\Services\Images\ImageResizer;
use App\Services\Images\ImagickImageResizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Single driver that exercises every ImageResizer implementation through the same
 * scenarios via a data provider. New backends added in the future only need a
 * matching arm in makeResizer().
 *
 * Note: extends Tests\TestCase (not PHPUnit\Framework\TestCase) because the
 * resizers raise UnsupportedImageFormatException with a translated message,
 * which needs the Laravel container's __() helper.
 */
class ImageResizerTest extends TestCase
{
    private const int MAX_EDGE = 400;

    /**
     * @return array<string, array{string}>
     */
    public static function backendProvider(): array
    {
        return [
            'gd' => ['gd'],
            'imagick' => ['imagick'],
        ];
    }

    /**
     * Cross-product of backend × GD-shared format. Used to verify resize
     * preserves format across every supported MIME on every backend.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function backendAndFormatProvider(): array
    {
        $cases = [];
        foreach (['gd', 'imagick'] as $backend) {
            foreach (['png', 'jpeg', 'webp', 'gif'] as $ext) {
                $cases["{$backend} {$ext}"] = [$backend, $ext, 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext)];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function supportedMimeProvider(): array
    {
        $cases = [];
        foreach (['image/png', 'image/jpeg', 'image/webp', 'image/gif'] as $mime) {
            $cases["gd {$mime}"] = ['gd', $mime];
            $cases["imagick {$mime}"] = ['imagick', $mime];
        }
        $cases['imagick image/heic'] = ['imagick', 'image/heic'];
        $cases['imagick image/heif'] = ['imagick', 'image/heif'];

        return $cases;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsupportedMimeProvider(): array
    {
        return [
            'gd heic' => ['gd', 'image/heic'],
            'gd heif' => ['gd', 'image/heif'],
            'gd tiff' => ['gd', 'image/tiff'],
            'gd text/plain' => ['gd', 'text/plain'],
            'imagick tiff' => ['imagick', 'image/tiff'],
            'imagick text/plain' => ['imagick', 'text/plain'],
        ];
    }

    private function makeResizer(string $name): ImageResizer
    {
        return match ($name) {
            'gd' => new GdImageResizer,
            'imagick' => extension_loaded('imagick')
                ? new ImagickImageResizer
                : $this->markTestSkipped('ext-imagick is not loaded on this host'),
        };
    }

    /**
     * Generate raw bytes for a solid-color image in the requested format.
     */
    private function imageBytes(string $ext, int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 50, 100, 200));
        ob_start();
        match ($ext) {
            'png' => imagepng($im),
            'jpg', 'jpeg' => imagejpeg($im),
            'webp' => imagewebp($im),
            'gif' => imagegif($im),
        };

        return (string) ob_get_clean();
    }

    /**
     * Generate a PNG with a transparent left half and opaque red right half so
     * alpha preservation can be verified by sampling a single edge pixel.
     */
    private function pngWithAlpha(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $width, $height, $transparent);
        $red = imagecolorallocatealpha($im, 255, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, (int) ($width / 2), $height, $red);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    #[DataProvider('backendProvider')]
    #[TestDox('resize returns input bytes unchanged when the image is already within the cap')]
    public function test_resize_returns_input_when_within_max_edge(string $backend): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);
        $bytes = $this->imageBytes('png', 200, 200);

        // Act
        $result = $resizer->resize($bytes, 'image/png', self::MAX_EDGE);

        // Assert
        $this->assertSame($bytes, $result);
    }

    #[DataProvider('backendAndFormatProvider')]
    #[TestDox('resize shrinks an oversized image to a 400px longest edge and keeps the source format')]
    public function test_resize_shrinks_oversized_image_and_preserves_format(string $backend, string $ext, string $mime): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);
        $bytes = $this->imageBytes($ext, 1000, 600);

        // Act
        $result = $resizer->resize($bytes, $mime, self::MAX_EDGE);

        // Assert
        $info = getimagesizefromstring($result);
        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
        $this->assertSame(240, $info[1]);
        $this->assertSame($mime, $info['mime']);
    }

    #[DataProvider('backendProvider')]
    #[TestDox('PNG alpha channel survives resize')]
    public function test_resize_preserves_png_alpha_channel(string $backend): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);
        $bytes = $this->pngWithAlpha(800, 400);

        // Act
        $result = $resizer->resize($bytes, 'image/png', self::MAX_EDGE);

        // Assert
        $resized = imagecreatefromstring($result);
        $this->assertNotFalse($resized);
        $rgba = imagecolorat($resized, imagesx($resized) - 1, 0);
        $alpha = ($rgba >> 24) & 0x7F;
        $this->assertGreaterThan(0, $alpha, 'Expected the right-edge pixel to retain its alpha after resize.');
    }

    /**
     * Cross-product of backend × non-image file type. PDF covers the realistic
     * case of a user uploading the wrong kind of file; plain text, JSON, and
     * empty bytes cover the edge cases.
     *
     * @return array<string, array{string, string}>
     */
    public static function nonImageBytesProvider(): array
    {
        $samples = [
            'plain text' => "Hello, world!\nThis is a text file, not an image.\n",
            'JSON document' => '{"not":"an image","just":"json"}',
            'PDF header' => "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n1 0 obj\n<<>>endobj\n",
            'empty bytes' => '',
        ];
        $cases = [];
        foreach (['gd', 'imagick'] as $backend) {
            foreach ($samples as $name => $bytes) {
                $cases["{$backend} {$name}"] = [$backend, $bytes];
            }
        }

        return $cases;
    }

    #[DataProvider('nonImageBytesProvider')]
    #[TestDox('resize throws UnsupportedImageFormatException when handed a non-image file')]
    public function test_resize_throws_for_non_image_file_types(string $backend, string $bytes): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);

        // Act + Assert
        $this->expectException(UnsupportedImageFormatException::class);
        $resizer->resize($bytes, 'image/png', self::MAX_EDGE);
    }

    #[DataProvider('supportedMimeProvider')]
    #[TestDox('supports returns true for each MIME the backend advertises')]
    public function test_supports_returns_true_for_supported_mime(string $backend, string $mime): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);

        // Act + Assert
        $this->assertTrue($resizer->supports($mime));
    }

    #[DataProvider('unsupportedMimeProvider')]
    #[TestDox('supports returns false for MIME types the backend does not handle')]
    public function test_supports_returns_false_for_unsupported_mime(string $backend, string $mime): void
    {
        // Arrange
        $resizer = $this->makeResizer($backend);

        // Act + Assert
        $this->assertFalse($resizer->supports($mime));
    }

    #[TestDox('Imagick rejects HEIC bytes whose container is valid but payload is garbage')]
    public function test_imagick_resize_throws_on_corrupted_heic_bytes(): void
    {
        // Arrange
        $resizer = $this->makeResizer('imagick');
        $heicHeader = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic";

        // Act + Assert
        $this->expectException(UnsupportedImageFormatException::class);
        $resizer->resize($heicHeader, 'image/heic', self::MAX_EDGE);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imagickContainerFormatProvider(): array
    {
        return [
            'heic' => ['image/heic'],
            'heif' => ['image/heif'],
        ];
    }

    #[DataProvider('imagickContainerFormatProvider')]
    #[TestDox('Imagick round-trips real HEIC/HEIF bytes through resize and emits the same container')]
    public function test_imagick_round_trips_heic_and_heif_formats(string $mime): void
    {
        // Arrange
        $resizer = $this->makeResizer('imagick');
        $format = self::MIME_TO_IMAGICK_FORMAT[$mime];
        // Some Imagick builds (notably CI runners) advertise HEIC/HEIF but lack a
        // functional libheif encoder, so probe the round-trip before exercising
        // the resizer instead of failing the test.
        try {
            $probe = new \Imagick;
            $probe->newImage(10, 10, 'red');
            $probe->setImageFormat($format);
            $probeBlob = $probe->getImageBlob();
            $probe->clear();
            $verifyProbe = new \Imagick;
            $verifyProbe->readImageBlob($probeBlob);
            $verifyProbe->clear();
        } catch (\ImagickException) {
            $this->markTestSkipped("Imagick build on this host cannot round-trip {$format}.");
        }
        $source = new \Imagick;
        $source->newImage(800, 400, 'red');
        $source->setImageFormat($format);
        $bytes = $source->getImageBlob();
        $source->clear();

        // Act
        $result = $resizer->resize($bytes, $mime, self::MAX_EDGE);

        // Assert
        $this->assertNotSame('', $result);
        $verify = new \Imagick;
        $verify->readImageBlob($result);
        $this->assertSame(400, $verify->getImageWidth());
        $this->assertSame(200, $verify->getImageHeight());
        $verify->clear();
    }

    private const array MIME_TO_IMAGICK_FORMAT = [
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function imagickMimeProvider(): array
    {
        return [
            'image/png' => ['image/png'],
            'image/jpeg' => ['image/jpeg'],
            'image/webp' => ['image/webp'],
            'image/gif' => ['image/gif'],
            'image/heic' => ['image/heic'],
            'image/heif' => ['image/heif'],
        ];
    }

    #[DataProvider('imagickMimeProvider')]
    #[TestDox('ImagickImageResizer reports unsupported for every MIME when the extension is not loaded')]
    public function test_imagick_supports_returns_false_when_extension_missing(string $mime): void
    {
        // Arrange
        $resizer = new class extends ImagickImageResizer
        {
            protected function isExtensionLoaded(): bool
            {
                return false;
            }
        };

        // Act + Assert
        $this->assertFalse($resizer->supports($mime));
    }
}
