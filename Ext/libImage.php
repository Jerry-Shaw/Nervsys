<?php

/**
 * Image Extension
 *
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

class libImage extends Factory
{
    //Support MIME-Type
    const MIME = ['image/gif', 'image/jpeg', 'image/png', 'image/bmp'];

    /**
     * Resize image to giving size
     *
     * @param string $file
     * @param int    $width
     * @param int    $height
     * @param bool   $crop
     *
     * @return bool
     */
    public function resize(string $file, int $width, int $height, bool $crop = false): bool
    {
        //Get image data
        $img_info = getimagesize($file);

        if (!in_array($img_info['mime'], self::MIME, true)) {
            return false;
        }

        //Get new size
        $img_size = $crop
            ? self::crop($img_info[0], $img_info[1], $width, $height)
            : self::zoom($img_info[0], $img_info[1], $width, $height);

        //No need to resize/crop
        if ($img_info[0] === $img_size['img_w'] && $img_info[1] === $img_size['img_h']) {
            return true;
        }

        //Process image
        $type      = substr($img_info['mime'], 6);
        $img_src   = call_user_func('imagecreatefrom' . $type, $file);
        $img_thumb = imagecreatetruecolor($img_size['img_w'], $img_size['img_h']);

        //Transparent for GIF/PNG
        switch ($img_info[2]) {
            case 1:
                //Deal with the transparent color in a GIF
                $transparent = imagecolorallocate($img_thumb, 0, 0, 0);
                imagefill($img_thumb, 0, 0, $transparent);
                imagecolortransparent($img_thumb, $transparent);
                break;

            case 3:
                //Deal with the transparent color in a PNG
                $transparent = imagecolorallocatealpha($img_thumb, 0, 0, 0, 127);
                imagealphablending($img_thumb, false);
                imagefill($img_thumb, 0, 0, $transparent);
                imagesavealpha($img_thumb, true);
                break;
        }

        imagecopyresampled(
            $img_thumb,
            $img_src,
            0,
            0,
            $img_size['img_x'],
            $img_size['img_y'],
            $img_size['img_w'],
            $img_size['img_h'],
            $img_size['src_w'],
            $img_size['src_h']
        );

        $result = call_user_func('image' . $type, $img_thumb, $file);

        imagedestroy($img_src);
        imagedestroy($img_thumb);

        unset($file, $width, $height, $crop, $img_info, $img_size, $type, $img_src, $img_thumb, $transparent);
        return $result;
    }

    /**
     * Rotate image
     *
     * @param string $file
     *
     * @return bool
     */
    public function rotate(string $file): bool
    {
        //Get EXIF data
        $img_exif = exif_read_data($file);

        //Check property
        if (false === $img_exif
            || !isset($img_exif['Orientation'])
            || !in_array($img_exif['MimeType'], self::MIME, true)
        ) {
            return false;
        }

        //Process image
        $type    = substr($img_exif['MimeType'], 6);
        $img_src = call_user_func('imagecreatefrom' . $type, $file);

        //Rotate image when needed
        switch ($img_exif['Orientation']) {
            case 8:
                $img_src = imagerotate($img_src, 90, 0);
                break;
            case 3:
                $img_src = imagerotate($img_src, 180, 0);
                break;
            case 6:
                $img_src = imagerotate($img_src, -90, 0);
                break;
            default:
                imagedestroy($img_src);
                return true;
        }

        $result = call_user_func('image' . $type, $img_src, $file);
        imagedestroy($img_src);

        unset($file, $img_exif, $type, $img_src);
        return $result;
    }

    /**
     * @param string $img_src
     * @param string $img_dst
     * @param string $text
     * @param string $font
     * @param int    $type
     * @param array  $options
     *
     * @return bool
     */
    public function addWatermarkFromString(string $img_src, string $img_dst, string $text, string $font, int $type = 0, array $options = []): bool
    {
        //Get image data
        $img_info = getimagesize($img_src);

        if (!in_array($img_info['mime'], self::MIME, true)) {
            return false;
        }

        //Process image
        $img_type = substr($img_info['mime'], 6);
        $src_img  = call_user_func('imagecreatefrom' . $img_type, $img_src);

        $text_len = mb_strlen($text, 'UTF-8');

        $font_size   = $options['size'] ?? 16;
        $text_angle  = $options['angle'] ?? 0;
        $text_width  = $text_len * $font_size;
        $font_color  = $options['color'] ?? [0, 0, 0, 100];
        $font_margin = $options['margin'] ?? [$text_width, $font_size * 3];

        $draw_color = imagecolorallocatealpha($src_img, $font_color[0], $font_color[1], $font_color[2], $font_color[3]);

        if (0 === $type) {
            imagettftext($src_img, $font_size, $text_angle, $font_margin[0] / 2 - $text_width / 2, $font_margin[1] / 2 - $font_size / 2, $draw_color, $font, $text);
        } elseif (1 === $type) {
            imagettftext($src_img, $font_size, $text_angle, $font_size, $font_size, $draw_color, $font, $text);
        } elseif (2 === $type) {
            imagettftext($src_img, $font_size, $text_angle, $font_margin[0] - $text_width - $font_size, $font_size, $draw_color, $font, $text);
        } elseif (3 === $type) {
            imagettftext($src_img, $font_size, $text_angle, $font_size, $font_margin[1] - $font_size * 2, $draw_color, $font, $text);
        } elseif (4 === $type) {
            imagettftext($src_img, $font_size, $text_angle, $font_margin[0] - $text_width - $font_size, $font_margin[1] - $font_size * 2, $draw_color, $font, $text);
        } else {
            for ($x = $font_size; $x < $img_info[0]; $x += $font_margin[0]) {
                for ($y = $font_size; $y < $img_info[1]; $y += $font_margin[1]) {
                    imagettftext($src_img, $font_size, $text_angle, $x, $y, $draw_color, $font, $text);
                }
            }

            unset($x, $y);
        }

        $result = call_user_func('image' . $img_type, $src_img, $img_dst);
        imagedestroy($src_img);

        unset($img_src, $img_dst, $text, $font, $type, $options, $img_info, $img_type, $src_img, $text_len, $font_size, $text_angle, $text_width, $font_color, $font_margin, $draw_color);
        return $result;
    }

    /**
     * Get image coordinates
     *
     * @param int $img_w
     * @param int $img_h
     * @param int $to_w
     * @param int $to_h
     *
     * @return array
     */
    private function crop(int $img_w, int $img_h, int $to_w, int $to_h): array
    {
        $size          = [];
        $size['img_w'] = &$to_w;
        $size['img_h'] = &$to_h;
        $size['src_w'] = $img_w;
        $size['src_h'] = $img_h;
        $size['img_x'] = $size['img_y'] = 0;

        //Incorrect width/height
        if (0 >= $img_w || 0 >= $img_h) {
            return $size;
        }

        //Calculate new width and height
        $ratio_img  = $img_w / $img_h;
        $ratio_need = $to_w / $to_h;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_h > $to_h) {
            $crop_w        = (int)($img_w - $img_h * $ratio_need);
            $size['img_x'] = (int)($crop_w / 2);
            $size['src_w'] = $img_w - $crop_w;
            unset($crop_w);
        } elseif (0 > $ratio_diff && $img_w > $to_w) {
            $crop_h        = (int)($img_h - $img_w / $ratio_need);
            $size['img_y'] = (int)($crop_h / 2);
            $size['src_h'] = $img_h - $size['img_y'] * 2;
            unset($crop_h);
        }

        unset($img_w, $img_h, $to_w, $to_h, $ratio_img, $ratio_need, $ratio_diff);
        return $size;
    }

    /**
     * Get new image size
     *
     * @param int $img_w
     * @param int $img_h
     * @param int $to_w
     * @param int $to_h
     *
     * @return array
     */
    private function zoom(int $img_w, int $img_h, int $to_w, int $to_h): array
    {
        $size = [];

        $size['img_x'] = $size['img_y'] = 0;
        $size['img_w'] = $size['src_w'] = $img_w;
        $size['img_h'] = $size['src_h'] = $img_h;

        //Incorrect width/height
        if (0 >= $img_w || 0 >= $img_h) {
            return $size;
        }

        //Calculate new width and height
        $ratio_img  = $img_w / $img_h;
        $ratio_need = $to_w / $to_h;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_w > $to_w) {
            $size['img_w'] = &$to_w;
            $size['img_h'] = (int)($to_w / $ratio_img);
        } elseif (0 > $ratio_diff && $img_h > $to_h) {
            $size['img_h'] = &$to_h;
            $size['img_w'] = (int)($to_h * $ratio_img);
        } elseif ($img_w > $to_w && $img_h > $to_h) {
            $size['img_w'] = &$to_w;
            $size['img_h'] = &$to_h;
        }

        unset($img_w, $img_h, $to_w, $to_h, $ratio_img, $ratio_need, $ratio_diff);
        return $size;
    }
}