<?php

/**
 * Image Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

class image
{
    //Support Mime-Type
    const mime = ['image/gif', 'image/jpeg', 'image/png', 'image/bmp'];

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
    public static function resize(string $file, int $width, int $height, bool $crop = false): bool
    {
        //Rotate image
        self::rotate($file);

        //Get image data
        $img_info = getimagesize($file);
        if (!in_array($img_info['mime'], self::mime, true)) {
            debug('Image Mime-type not support!');
            return false;
        }

        //Get new size
        $img_size = [];
        $crop ? self::img_crop($img_size, $img_info[0], $img_info[1], $width, $height) : self::img_size($img_size, $img_info[0], $img_info[1], $width, $height);

        //No need to resize/crop
        if ($img_info[0] === $img_size['img_w'] && $img_info[1] === $img_size['img_h']) return true;

        //Process image
        $type = substr($img_info['mime'], 6);
        $img_create = 'imagecreatefrom' . $type;
        $img_output = 'image' . $type;
        $img_source = $img_create($file);
        $img_thumb = imagecreatetruecolor($img_size['img_w'], $img_size['img_h']);

        //Transparent for GIF/PNG
        switch ($img_info[2]) {
            case 1://Deal with the transparent color in a GIF
                $transparent = imagecolorallocate($img_thumb, 0, 0, 0);
                imagefill($img_thumb, 0, 0, $transparent);
                imagecolortransparent($img_thumb, $transparent);
                break;
            case 3://Deal with the transparent color in a PNG
                $transparent = imagecolorallocatealpha($img_thumb, 0, 0, 0, 127);
                imagealphablending($img_thumb, false);
                imagefill($img_thumb, 0, 0, $transparent);
                imagesavealpha($img_thumb, true);
                break;
        }

        imagecopyresampled($img_thumb, $img_source, 0, 0, $img_size['img_x'], $img_size['img_y'], $img_size['img_w'], $img_size['img_h'], $img_size['src_w'], $img_size['src_h']);
        $result = $img_output($img_thumb, $file);
        imagedestroy($img_source);
        imagedestroy($img_thumb);

        unset($file, $width, $height, $crop, $img_info, $img_size, $type, $img_create, $img_output, $img_source, $img_thumb, $transparent);
        return $result;
    }

    /**
     * Rotate image
     *
     * @param string $file
     */
    private static function rotate(string $file): void
    {
        //Get EXIF data
        $img_exif = exif_read_data($file);
        if (false === $img_exif || !isset($img_exif['Orientation'])) return;
        if (!in_array($img_exif['MimeType'], self::mime, true)) {
            debug('Image Mime-type not support!');
            return;
        }

        //Process image
        $type = substr($img_exif['MimeType'], 6);
        $img_create = 'imagecreatefrom' . $type;
        $img_output = 'image' . $type;
        $img_source = $img_create($file);

        //Rotate image when needed
        switch ($img_exif['Orientation']) {
            case 8:
                $img_source = imagerotate($img_source, 90, 0);
                break;
            case 3:
                $img_source = imagerotate($img_source, 180, 0);
                break;
            case 6:
                $img_source = imagerotate($img_source, -90, 0);
                break;
            default:
                imagedestroy($img_source);
                return;
        }

        $img_output($img_source, $file);
        imagedestroy($img_source);

        unset($file, $img_exif, $type, $img_create, $img_output, $img_source);
    }

    /**
     * Get image coordinates
     *
     * @param array $size
     * @param int   $img_w
     * @param int   $img_h
     * @param int   $to_w
     * @param int   $to_h
     */
    private static function img_crop(array &$size, int $img_w, int $img_h, int $to_w, int $to_h): void
    {
        $size['img_w'] = &$to_w;
        $size['img_h'] = &$to_h;
        $size['src_w'] = $img_w;
        $size['src_h'] = $img_h;
        $size['img_x'] = $size['img_y'] = 0;

        //Incorrect width/height
        if (0 >= $img_w || 0 >= $img_h) return;

        //Calculate new width and height
        $ratio_img = $img_w / $img_h;
        $ratio_need = $to_w / $to_h;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_h > $to_h) {
            $crop_w = (int)($img_w - $img_h * $ratio_need);
            $size['img_x'] = (int)($crop_w / 2);
            $size['src_w'] = $img_w - $crop_w;
            unset($crop_w);
        } elseif (0 > $ratio_diff && $img_w > $to_w) {
            $crop_h = (int)($img_h - $img_w / $ratio_need);
            $size['img_y'] = (int)($crop_h / 2);
            $size['src_h'] = $img_h - $size['img_y'] * 2;
            unset($crop_h);
        }

        unset($img_w, $img_h, $to_w, $to_h, $ratio_img, $ratio_need, $ratio_diff);
    }

    /**
     * Get new image size
     *
     * @param array $size
     * @param int   $img_w
     * @param int   $img_h
     * @param int   $to_w
     * @param int   $to_h
     */
    private static function img_size(array &$size, int $img_w, int $img_h, int $to_w, int $to_h): void
    {
        $size['img_x'] = $size['img_y'] = 0;
        $size['img_w'] = $size['src_w'] = $img_w;
        $size['img_h'] = $size['src_h'] = $img_h;

        //Incorrect width/height
        if (0 >= $img_w || 0 >= $img_h) return;

        //Calculate new width and height
        $ratio_img = $img_w / $img_h;
        $ratio_need = $to_w / $to_h;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_w > $to_w) {
            $size['img_w'] = &$to_w;
            $size['img_h'] = (int)($to_w / $ratio_img);
        } elseif (0 > $ratio_diff && $img_h > $to_h) {
            $size['img_h'] = &$to_h;
            $size['img_w'] = (int)($to_h * $ratio_img);
        } elseif (0 === $ratio_diff && $img_w > $to_w && $img_h > $to_h) {
            $size['img_w'] = &$to_w;
            $size['img_h'] = &$to_h;
        }

        unset($img_w, $img_h, $to_w, $to_h, $ratio_img, $ratio_need, $ratio_diff);
    }
}