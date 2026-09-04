<?php

namespace App\Services;

use RuntimeException;

class SpecialtyImageProcessor
{
    public const WIDTH = 1200;
    public const HEIGHT = 800;

    public function convert(string $source, string $destination): void
    {
        $input = @imagecreatefromstring((string) file_get_contents($source));
        if ($input === false) {
            throw new RuntimeException("Unable to decode specialty image: {$source}");
        }

        try {
            $sourceWidth = imagesx($input);
            $sourceHeight = imagesy($input);
            $scale = max(self::WIDTH / $sourceWidth, self::HEIGHT / $sourceHeight);
            $cropWidth = (int) round(self::WIDTH / $scale);
            $cropHeight = (int) round(self::HEIGHT / $scale);
            $sourceX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
            $sourceY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));

            $output = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
            imagealphablending($output, false);
            imagesavealpha($output, true);
            imagecopyresampled($output, $input, 0, 0, $sourceX, $sourceY, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);
            imagewebp($output, $destination, 82);
            imagedestroy($output);
        } finally {
            imagedestroy($input);
        }
    }
}
