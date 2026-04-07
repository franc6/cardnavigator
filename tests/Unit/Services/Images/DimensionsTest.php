<?php

namespace Tests\Unit\Services\Images;

use App\Services\Images\Dimensions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic test for Dimensions::scale.
 *
 * Extends PHPUnit\Framework\TestCase (not Tests\TestCase) because the class
 * under test has no Laravel container dependencies — see CLAUDE.md.
 */
class DimensionsTest extends TestCase
{
    /**
     * @return array<string, array{int, int, int, int, int}>
     */
    public static function scaleProvider(): array
    {
        return [
            'landscape, scales by longest edge (width)' => [1000, 600, 400, 400, 240],
            'portrait, scales by longest edge (height)' => [600, 1000, 400, 240, 400],
            'square at the cap' => [400, 400, 400, 400, 400],
            'square over the cap' => [1000, 1000, 400, 400, 400],
            'landscape exactly at the cap' => [400, 200, 400, 400, 200],
            'portrait exactly at the cap' => [200, 400, 400, 200, 400],
            'landscape below the cap (no scaling)' => [320, 240, 400, 320, 240],
            'portrait below the cap (no scaling)' => [240, 320, 400, 240, 320],
            'landscape one pixel over the cap' => [401, 200, 400, 400, 200],
            'portrait one pixel over the cap' => [200, 401, 400, 200, 400],
            'extreme landscape (very wide)' => [4000, 100, 400, 400, 10],
            'extreme portrait (very tall)' => [100, 4000, 400, 10, 400],
        ];
    }

    #[DataProvider('scaleProvider')]
    #[TestDox('scale clamps the longest edge to maxEdge and preserves aspect ratio')]
    public function test_scale_clamps_to_max_edge_preserving_aspect_ratio(int $w, int $h, int $maxEdge, int $expectedW, int $expectedH): void
    {
        // Act
        [$outW, $outH] = Dimensions::scale($w, $h, $maxEdge);

        // Assert
        $this->assertSame($expectedW, $outW);
        $this->assertSame($expectedH, $outH);
    }
}
