<?php

namespace App\Services\Images;

/**
 * Pure helpers for image dimension arithmetic shared by the resizer backends.
 */
final class Dimensions
{
    /**
     * Compute resized dimensions preserving aspect ratio and clamped to $maxEdge.
     *
     * @param  int  $w  Source width in pixels.
     * @param  int  $h  Source height in pixels.
     * @param  int  $maxEdge  Maximum length (in pixels) of the longest edge.
     * @return array{0: int, 1: int} The target [width, height]; identical to the source when no scaling is required.
     */
    public static function scale(int $w, int $h, int $maxEdge): array
    {
        $longest = max($w, $h);

        if ($longest <= $maxEdge) {
            return [$w, $h];
        }

        $scale = $maxEdge / $longest;

        return [(int) round($w * $scale), (int) round($h * $scale)];
    }
}
