<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedImageFormatException;
use App\Services\CardImageService;
use App\Services\Images\ImageResizer;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Exercises the service-level concerns of CardImageService — MIME detection,
 * resizer selection, and the array-shape contract — using mock resizers so
 * backend behaviour is owned by ImageResizerTest.
 *
 * Note: extends Tests\TestCase (not PHPUnit\Framework\TestCase) because the
 * service uses the __() translation helper, which needs the Laravel container.
 */
class CardImageServiceTest extends TestCase
{
    private const int MAX_EDGE = 400;

    private function uploadedFileFromBytes(string $bytes, string $filename = 'card.bin', string $mime = 'application/octet-stream'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cardimg');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $filename, $mime, null, true);
    }

    private function pngBytes(int $width = 120, int $height = 120): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 50, 100, 200));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /**
     * Build an ISO BMFF header `ftyp<brand>` followed by enough padding for the
     * 12-byte detection prefix. Just enough to exercise detectMime().
     */
    private function isoBmffBytes(string $brand): string
    {
        return "\x00\x00\x00\x18ftyp" . $brand . "\x00\x00\x00\x00mif1heic";
    }

    #[TestDox('fromUpload returns base64-encoded data and the detected MIME')]
    public function test_fromUpload_returns_base64_data_and_detected_mime(): void
    {
        // Arrange
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldReceive('supports')->with('image/png')->andReturnTrue();
        $resizer->shouldReceive('resize')
            ->once()
            ->with(Mockery::type('string'), 'image/png', self::MAX_EDGE)
            ->andReturn('RESIZED');
        $service = new CardImageService([$resizer]);
        $file = $this->uploadedFileFromBytes($this->pngBytes(), 'card.png', 'image/png');

        // Act
        $result = $service->fromUpload($file);

        // Assert
        $this->assertSame(['data' => base64_encode('RESIZED'), 'mime' => 'image/png'], $result);
    }

    #[TestDox('fromUpload skips resizers whose supports() returns false and uses the next one')]
    public function test_fromUpload_falls_through_to_next_supporting_resizer(): void
    {
        // Arrange
        $first = Mockery::mock(ImageResizer::class);
        $first->shouldReceive('supports')->with('image/png')->andReturnFalse();
        $first->shouldNotReceive('resize');

        $second = Mockery::mock(ImageResizer::class);
        $second->shouldReceive('supports')->with('image/png')->andReturnTrue();
        $second->shouldReceive('resize')->once()->andReturn('SECOND');

        $service = new CardImageService([$first, $second]);
        $file = $this->uploadedFileFromBytes($this->pngBytes(), 'card.png', 'image/png');

        // Act
        $result = $service->fromUpload($file);

        // Assert
        $this->assertSame(base64_encode('SECOND'), $result['data']);
        $this->assertSame('image/png', $result['mime']);
    }

    #[TestDox('fromUpload throws when no resizer supports the detected MIME')]
    public function test_fromUpload_throws_when_no_resizer_supports_mime(): void
    {
        // Arrange
        $first = Mockery::mock(ImageResizer::class);
        $first->shouldReceive('supports')->with('image/png')->andReturnFalse();
        $first->shouldNotReceive('resize');

        $second = Mockery::mock(ImageResizer::class);
        $second->shouldReceive('supports')->with('image/png')->andReturnFalse();
        $second->shouldNotReceive('resize');

        $service = new CardImageService([$first, $second]);
        $file = $this->uploadedFileFromBytes($this->pngBytes(), 'card.png', 'image/png');

        // Act + Assert
        $this->expectException(UnsupportedImageFormatException::class);
        $this->expectExceptionMessage('This image format is not supported on this server.');
        $service->fromUpload($file);
    }

    #[TestDox('fromUpload throws when the bytes are not a recognised image format')]
    public function test_fromUpload_throws_when_mime_cannot_be_detected(): void
    {
        // Arrange
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldNotReceive('supports');
        $resizer->shouldNotReceive('resize');

        $service = new CardImageService([$resizer]);
        $file = $this->uploadedFileFromBytes('not an image at all', 'note.txt', 'text/plain');

        // Act + Assert
        $this->expectException(UnsupportedImageFormatException::class);
        $this->expectExceptionMessage('This file does not appear to be a supported image.');
        $service->fromUpload($file);
    }

    #[TestDox('fromUpload throws when the uploaded file cannot be read off disk')]
    public function test_fromUpload_throws_when_file_cannot_be_read(): void
    {
        // Arrange
        $tmp = tempnam(sys_get_temp_dir(), 'cardimg');
        file_put_contents($tmp, 'placeholder');
        $file = new class($tmp, 'card.png', 'image/png', null, true) extends UploadedFile
        {
            public function getRealPath(): string
            {
                return '/this/path/does/not/exist/cardnavigator-test';
            }
        };
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldNotReceive('supports');
        $resizer->shouldNotReceive('resize');
        $service = new CardImageService([$resizer]);

        // Act + Assert
        $this->expectException(UnsupportedImageFormatException::class);
        $this->expectExceptionMessage('The uploaded image could not be read.');
        $service->fromUpload($file);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function heicBrandProvider(): array
    {
        return [
            'heic' => ['heic'],
            'heix' => ['heix'],
            'heim' => ['heim'],
            'heis' => ['heis'],
            'hevc' => ['hevc'],
            'hevx' => ['hevx'],
        ];
    }

    #[DataProvider('heicBrandProvider')]
    #[TestDox('detectMime classifies every HEIC ISO BMFF brand as image/heic')]
    public function test_detectMime_recognises_heic_brand_variants(string $brand): void
    {
        // Arrange
        $captured = null;
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldReceive('supports')->andReturnTrue();
        $resizer->shouldReceive('resize')
            ->once()
            ->andReturnUsing(function (string $bytes, string $mime) use (&$captured) {
                $captured = $mime;

                return 'OK';
            });
        $service = new CardImageService([$resizer]);
        $file = $this->uploadedFileFromBytes($this->isoBmffBytes($brand), 'photo.heic', 'image/heic');

        // Act
        $service->fromUpload($file);

        // Assert
        $this->assertSame('image/heic', $captured);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function heifBrandProvider(): array
    {
        return [
            'mif1' => ['mif1'],
            'msf1' => ['msf1'],
        ];
    }

    #[DataProvider('heifBrandProvider')]
    #[TestDox('detectMime classifies the mif1 and msf1 ISO BMFF brands as image/heif')]
    public function test_detectMime_recognises_heif_brand_variants(string $brand): void
    {
        // Arrange
        $captured = null;
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldReceive('supports')->andReturnTrue();
        $resizer->shouldReceive('resize')
            ->once()
            ->andReturnUsing(function (string $bytes, string $mime) use (&$captured) {
                $captured = $mime;

                return 'OK';
            });
        $service = new CardImageService([$resizer]);
        $file = $this->uploadedFileFromBytes($this->isoBmffBytes($brand), 'photo.heif', 'image/heif');

        // Act
        $service->fromUpload($file);

        // Assert
        $this->assertSame('image/heif', $captured);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function gdMimeProvider(): array
    {
        return [
            'png' => ['png', 'image/png'],
            'jpeg' => ['jpg', 'image/jpeg'],
            'webp' => ['webp', 'image/webp'],
            'gif' => ['gif', 'image/gif'],
        ];
    }

    #[DataProvider('gdMimeProvider')]
    #[TestDox('detectMime classifies raw bytes by signature for the four GD-shared formats')]
    public function test_detectMime_recognises_gd_formats_from_raw_bytes(string $ext, string $expectedMime): void
    {
        // Arrange
        $im = imagecreatetruecolor(60, 60);
        imagefilledrectangle($im, 0, 0, 60, 60, imagecolorallocate($im, 10, 20, 30));
        ob_start();
        match ($ext) {
            'png' => imagepng($im),
            'jpg' => imagejpeg($im),
            'webp' => imagewebp($im),
            'gif' => imagegif($im),
        };
        $bytes = (string) ob_get_clean();

        $captured = null;
        $resizer = Mockery::mock(ImageResizer::class);
        $resizer->shouldReceive('supports')->andReturnTrue();
        $resizer->shouldReceive('resize')
            ->once()
            ->andReturnUsing(function (string $b, string $mime) use (&$captured) {
                $captured = $mime;

                return 'OK';
            });
        $service = new CardImageService([$resizer]);
        $file = $this->uploadedFileFromBytes($bytes, 'card.' . $ext, $expectedMime);

        // Act
        $service->fromUpload($file);

        // Assert
        $this->assertSame($expectedMime, $captured);
    }
}
