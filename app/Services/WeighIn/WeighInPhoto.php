<?php

namespace App\Services\WeighIn;

use RuntimeException;

/**
 * Normalizes phone photos for storage and reading: fixes EXIF rotation, limits the size and re-encodes
 * as JPEG (which also strips location and device metadata).
 */
class WeighInPhoto
{
    public const MAX_EDGE = 1600;

    public static function normalize(string $path): string
    {
        $contents = file_get_contents($path);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('The photo could not be read. Use a JPG or PNG image.');
        }

        $image = self::applyOrientation($image, $path);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_EDGE / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            $image = $resized === false ? $image : $resized;
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    private static function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }
}
