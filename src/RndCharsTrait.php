<?php

declare(strict_types=1);

namespace SugiPHP\Captcha;

use RuntimeException;

/**
 * Random characters code generation and drawing (using GD), shared between the CAPTCHA implementations.
 * Expects $config (charset, length, width, height, font) and $code properties in the class.
 *
 * @internal
 */
trait RndCharsTrait
{
    /**
     * Generates a random code.
     */
    private function generateCode(): string
    {
        $code = '';

        $charset = $this->config['charset'];
        $cnt = mb_strlen($charset, 'UTF-8');

        for ($i = 0; $i < $this->config['length']; $i++) {
            $code .= mb_substr($charset, random_int(0, $cnt - 1), 1, 'UTF-8');
        }

        return $code;
    }

    /**
     * Draws a CAPTCHA code on the image canvas.
     */
    private function drawImage()
    {
        $image = imagecreatetruecolor($this->config['width'], $this->config['height']);
        $bg = imagecolorallocate($image, mt_rand(225, 255), mt_rand(225, 255), mt_rand(225, 255));
        imagefill($image, 0, 0, $bg);

        $code = $this->code;
        $width = $this->config['width'];
        $height = $this->config['height'];
        $font = $this->config['font'];
        $len = mb_strlen($code, 'UTF-8');
        if ($len === 0) {
            throw new RuntimeException('Code length cannot be zero');
        }
        $size = max(8, (int) ($width / $len) - mt_rand(1, 3)); // Ensure minimum font size of 8px

        $box = imagettfbbox($size, 0, $font, $code);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = (int) (($width - $textWidth) / 2);
        $y = (int) (($height - $textHeight) / 2 + $size);

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($code, $i, 1, 'UTF-8');
            $box = imagettfbbox($size, 0, $font, $char);
            $w = $box[2] - $box[0];
            $angle = mt_rand(-10, 10);
            $offset = mt_rand(-2, 2);
            $color = imagecolorallocate($image, mt_rand(0, 125), mt_rand(0, 125), mt_rand(0, 125));
            imagettftext($image, $size, $angle, $x, $y + $offset, $color, $font, $char);
            $x += $w;
        }

        return $image;
    }
}
