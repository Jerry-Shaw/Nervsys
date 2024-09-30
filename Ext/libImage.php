<?php

/**
 * Image Extension
 *
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
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
    /**
     * Create blank image canvas
     *
     * @param int   $width
     * @param int   $height
     * @param bool  $alpha_blending
     * @param bool  $save_alpha
     * @param array $fill_color
     *
     * @return \GdImage
     */
    public function createImage(int $width, int $height, bool $alpha_blending = false, bool $save_alpha = true, array $fill_color = [255, 255, 255]): \GdImage
    {
        $gd_image = imagecreatetruecolor($width, $height);

        imagealphablending($gd_image, $alpha_blending);
        imagesavealpha($gd_image, $save_alpha);

        if ($save_alpha) {
            $color = imagecolorallocatealpha($gd_image, $fill_color[0], $fill_color[1], $fill_color[2], 127);
            imagecolortransparent($gd_image, $color);
        } else {
            $color = imagecolorallocate($gd_image, $fill_color[0], $fill_color[1], $fill_color[2]);
        }

        imagefill($gd_image, 0, 0, $color);

        unset($width, $height, $alpha_blending, $save_alpha, $fill_color, $color);
        return $gd_image;
    }

    /**
     * Create GDImage from file
     *
     * @param string $file
     * @param bool   $alpha_blending
     * @param bool   $save_alpha
     *
     * @return \GdImage
     * @throws \Exception
     */
    public function createImageFrom(string $file, bool $alpha_blending = false, bool $save_alpha = true): \GdImage
    {
        $mime_type = $this->getImageMimeType($file);
        $gd_image  = ('imagecreatefrom' . $mime_type)($file);

        imagealphablending($gd_image, $alpha_blending);
        imagesavealpha($gd_image, $save_alpha);

        unset($file, $alpha_blending, $save_alpha, $mime_type);
        return $gd_image;
    }

    /**
     * Resize/Corp image to giving size
     *
     * @param string $file
     * @param int    $width
     * @param int    $height
     * @param bool   $crop
     *
     * @return bool
     * @throws \Exception
     */
    public function resize(string $file, int $width, int $height, bool $crop = false): bool
    {
        $mime_type = $this->getImageMimeType($file);

        $gd_image = $this->createImageFrom($file);
        $gd_image = $this->gd_resize($gd_image, $width, $height, $crop);

        $result = ('image' . $mime_type)($gd_image, $file);

        imagedestroy($gd_image);

        unset($file, $width, $height, $crop, $mime_type, $gd_image);
        return $result;
    }

    /**
     * Rotate image to giving degrees
     *
     * @param string $file
     * @param int    $angle
     * @param array  $fill_color
     *
     * @return bool
     * @throws \Exception
     */
    public function rotate(string $file, int $angle = 0, array $fill_color = [255, 255, 255]): bool
    {
        if (0 === $angle) {
            try {
                $exif_data = exif_read_data($file);

                if (false === $exif_data) {
                    throw new \Exception('EXIF: File not supported!');
                }
            } catch (\Throwable) {
                throw new \Exception('EXIF: File not supported!');
            }

            if (!isset($exif_data['Orientation'])) {
                unset($file, $angle, $fill_color, $exif_data);
                return false;
            }

            switch ($exif_data['Orientation']) {
                case 3:
                    $angle = 180;
                    break;
                case 6:
                    $angle = -90;
                    break;
                case 8:
                    $angle = 90;
                    break;
                default:
                    return true;
            }

            unset($exif_data);
        }

        $gd_image = $this->createImageFrom($file);
        $color    = imagecolorallocatealpha($gd_image, $fill_color[0], $fill_color[1], $fill_color[2], 127);

        imagecolortransparent($gd_image, $color);
        imagefill($gd_image, 0, 0, $color);

        $rotated_image = imagerotate($gd_image, $angle, $color);

        imagealphablending($rotated_image, false);
        imagesavealpha($rotated_image, true);
        imagecolortransparent($rotated_image, $color);
        imagefill($rotated_image, 0, 0, $color);

        $gd_type = $this->getImageMimeType($file);
        $result  = ('image' . $gd_type)($rotated_image, $file);

        imagedestroy($gd_image);
        imagedestroy($rotated_image);

        unset($file, $angle, $fill_color, $gd_image, $color, $rotated_image, $gd_type);
        return $result;
    }

    /**
     * Add watermark from image, merged to jpeg
     *
     * @param string $img_src       source image
     * @param string $img_dst       save to image file
     * @param string $img_watermark watermark image
     * @param string $layout        layout type: top-left/top-right/top-center/bottom-left/bottom-right/bottom-center/center/fill
     * @param array  $options       options:
     *                              'width' => $image_width,
     *                              'height' => $image_height,
     *                              'angle' => 0,
     *                              'alpha' => 10, (0-100, 0: transparent; 100: opaque)
     *                              'margin' => [$watermark_width/3, $watermark_height/3],
     *                              'fill_color' => [255, 255, 255]
     * @param int    $jpeg_quality  image quality, default to 80 (High quality)
     *
     * @return bool
     * @throws \Exception
     */
    public function addWatermarkFromImage(string $img_src, string $img_dst, string $img_watermark, string $layout = 'fill', array $options = [], int $jpeg_quality = 80): bool
    {
        $gd_image     = $this->createImageFrom($img_src);
        $gd_watermark = $this->createImageFrom($img_watermark);

        $gd_merged = $this->gd_addWatermark(
            $gd_image,
            $gd_watermark,
            $options['width'] ?? 0,
            $options['height'] ?? 0,
            $options['angle'] ?? 0,
            $options['alpha'] ?? 10,
            $layout,
            $options['margin'][0] ?? (int)(imagesx($gd_watermark) / 3),
            $options['margin'][1] ?? (int)(imagesy($gd_watermark) / 3),
            $options['fill_color'] ?? [255, 255, 255]
        );

        $result = imagejpeg($gd_merged, $img_dst, $jpeg_quality);

        imagedestroy($gd_image);
        imagedestroy($gd_watermark);
        imagedestroy($gd_merged);

        unset($img_src, $img_dst, $img_watermark, $layout, $options, $jpeg_quality, $gd_image, $gd_watermark, $gd_merged);
        return $result;
    }

    /**
     * Add watermark from text string, merged to jpeg
     *
     * @param string $img_src      source image
     * @param string $img_dst      save to image file
     * @param string $text         watermark text string
     * @param string $font         ttf font file
     * @param string $layout       layout type: top-left/top-right/top-center/bottom-left/bottom-right/bottom-center/center/fill
     * @param array  $options      options:
     *                             'font_size' => 16,
     *                             'font_color' => [0, 0, 0],
     *                             'width' => $font_size * $word_count = $text_width,
     *                             'height' => $font_size,
     *                             'angle' => 0,
     *                             'alpha' => 10, (0-100, 0: transparent; 100: opaque)
     *                             'margin' => [$text_width/3, $font_size*3],
     *                             'fill_color' => [255, 255, 255]
     * @param int    $jpeg_quality image quality, default to 80 (High quality)
     *
     * @return bool
     * @throws \Exception
     */
    public function addWatermarkFromString(string $img_src, string $img_dst, string $text, string $font, string $layout = 'fill', array $options = [], int $jpeg_quality = 80): bool
    {
        $font_size = $options['font_size'] ?? 16;
        $text_box  = imagettfbbox($font_size, 0, $font, $text);

        $min_x = min($text_box[0], $text_box[2], $text_box[4], $text_box[6]);
        $min_y = min($text_box[1], $text_box[3], $text_box[5], $text_box[7]);
        $max_x = max($text_box[0], $text_box[2], $text_box[4], $text_box[6]);
        $max_y = max($text_box[1], $text_box[3], $text_box[5], $text_box[7]);

        $padding = min(16, ceil($font_size / 2));
        $offset  = floor($padding / 2);

        $text_width  = $padding + $max_x - $min_x;
        $text_height = $padding + $max_y - $min_y;

        $gd_watermark = $this->createImage($text_width, $text_height);

        $font_color = imagecolorallocate(
            $gd_watermark,
            $options['font_color'][0] ?? 0,
            $options['font_color'][1] ?? 0,
            $options['font_color'][2] ?? 0
        );

        imagettftext($gd_watermark, $font_size, 0, $offset, $offset + abs($min_y), $font_color, $font, $text);

        $gd_image  = $this->createImageFrom($img_src);
        $gd_merged = $this->gd_addWatermark(
            $gd_image,
            $gd_watermark,
            $options['width'] ?? $text_width,
            $options['height'] ?? $text_height,
            $options['angle'] ?? 0,
            $options['alpha'] ?? 10,
            $layout,
            $options['margin'][0] ?? (int)($text_width / 2),
            $options['margin'][1] ?? $font_size * 3,
            $options['fill_color'] ?? [255, 255, 255]
        );

        $result = imagejpeg($gd_merged, $img_dst, $jpeg_quality);

        imagedestroy($gd_watermark);
        imagedestroy($gd_image);
        imagedestroy($gd_merged);

        unset($img_src, $img_dst, $text, $font, $layout, $options, $jpeg_quality, $font_size, $text_box, $min_x, $min_y, $max_x, $max_y, $padding, $offset, $text_width, $text_height, $gd_watermark, $font_color, $gd_image, $gd_merged);
        return $result;
    }

    /**
     * Get image size for cropping
     *
     * @param int $img_width
     * @param int $img_height
     * @param int $to_width
     * @param int $to_height
     *
     * @return array
     */
    public function getCropSize(int $img_width, int $img_height, int $to_width, int $to_height): array
    {
        $size               = [];
        $size['dst_width']  = &$to_width;
        $size['dst_height'] = &$to_height;
        $size['src_width']  = $img_width;
        $size['src_height'] = $img_height;
        $size['position_x'] = $size['position_y'] = 0;

        //Incorrect width/height
        if (0 >= $img_width || 0 >= $img_height) {
            return $size;
        }

        //Calculate new width and height
        $ratio_img  = $img_width / $img_height;
        $ratio_need = $to_width / $to_height;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_height > $to_height) {
            $crop_w             = (int)($img_width - $img_height * $ratio_need);
            $size['position_x'] = (int)($crop_w / 2);
            $size['src_width']  = $img_width - $crop_w;
            unset($crop_w);
        } elseif (0 > $ratio_diff && $img_width > $to_width) {
            $crop_h             = (int)($img_height - $img_width / $ratio_need);
            $size['position_y'] = (int)($crop_h / 2);
            $size['src_height'] = $img_height - $size['position_y'] * 2;
            unset($crop_h);
        }

        unset($img_width, $img_height, $to_width, $to_height, $ratio_img, $ratio_need, $ratio_diff);
        return $size;
    }

    /**
     * Get image size for zooming
     *
     * @param int $img_width
     * @param int $img_height
     * @param int $to_width
     * @param int $to_height
     *
     * @return array
     */
    public function getZoomSize(int $img_width, int $img_height, int $to_width, int $to_height): array
    {
        $size = [];

        $size['position_x'] = $size['position_y'] = 0;
        $size['dst_width']  = $size['src_width'] = $img_width;
        $size['dst_height'] = $size['src_height'] = $img_height;

        //Incorrect width/height
        if (0 >= $img_width || 0 >= $img_height) {
            return $size;
        }

        //Calculate new width and height
        $ratio_img  = $img_width / $img_height;
        $ratio_need = $to_width / $to_height;
        $ratio_diff = round($ratio_img - $ratio_need, 2);

        if (0 < $ratio_diff && $img_width > $to_width) {
            $size['dst_width']  = &$to_width;
            $size['dst_height'] = (int)($to_width / $ratio_img);
        } elseif (0 > $ratio_diff && $img_height > $to_height) {
            $size['dst_height'] = &$to_height;
            $size['dst_width']  = (int)($to_height * $ratio_img);
        } elseif ($img_width > $to_width && $img_height > $to_height) {
            $size['dst_width']  = &$to_width;
            $size['dst_height'] = &$to_height;
        }

        unset($img_width, $img_height, $to_width, $to_height, $ratio_img, $ratio_need, $ratio_diff);
        return $size;
    }

    /**
     * @param \GdImage $gd_image
     * @param int      $width
     * @param int      $height
     * @param bool     $crop
     *
     * @return \GdImage
     */
    protected function gd_resize(\GdImage $gd_image, int $width, int $height, bool $crop = false): \GdImage
    {
        if (0 === $width || 0 === $height) {
            unset($width, $height, $crop);
            return $gd_image;
        }

        $gd_width  = imagesx($gd_image);
        $gd_height = imagesy($gd_image);

        $img_size = $crop
            ? $this->getCropSize($gd_width, $gd_height, $width, $height)
            : $this->getZoomSize($gd_width, $gd_height, $width, $height);

        if ($img_size['dst_width'] === $gd_width && $img_size['dst_height'] === $gd_height) {
            unset($width, $height, $crop, $gd_width, $gd_height, $img_size);
            return $gd_image;
        }

        $dst_image = $this->createImage($img_size['dst_width'], $img_size['dst_height']);

        imagecopyresampled(
            $dst_image,
            $gd_image,
            0,
            0,
            $img_size['position_x'],
            $img_size['position_y'],
            $img_size['dst_width'],
            $img_size['dst_height'],
            $img_size['src_width'],
            $img_size['src_height']
        );

        imagedestroy($gd_image);

        unset($gd_image, $width, $height, $crop, $gd_width, $gd_height, $img_size);
        return $dst_image;
    }

    /**
     * @param \GdImage $gd_image
     * @param \GdImage $gd_watermark
     * @param int      $watermark_width
     * @param int      $watermark_height
     * @param int      $watermark_angle
     * @param int      $watermark_alpha
     * @param string   $layout
     * @param int      $margin_right
     * @param int      $margin_bottom
     * @param array    $fill_color
     *
     * @return \GdImage
     */
    protected function gd_addWatermark(\GdImage $gd_image, \GdImage $gd_watermark, int $watermark_width, int $watermark_height, int $watermark_angle = 0, int $watermark_alpha = 10, string $layout = 'fill', int $margin_right = 0, int $margin_bottom = 0, array $fill_color = [255, 255, 255]): \GdImage
    {
        $gd_watermark = $this->gd_resize($gd_watermark, $watermark_width, $watermark_height);

        if (0 !== $watermark_angle) {
            $transparent  = imagecolorallocatealpha($gd_watermark, $fill_color[0], $fill_color[1], $fill_color[2], 127);
            $gd_watermark = imagerotate($gd_watermark, $watermark_angle, $transparent);

            unset($transparent);
        }

        $canvas_width     = imagesx($gd_image);
        $canvas_height    = imagesy($gd_image);
        $watermark_width  = imagesx($gd_watermark);
        $watermark_height = imagesy($gd_watermark);

        $position_list = $this->getWatermarkPositions($layout, $canvas_width, $canvas_height, $watermark_width, $watermark_height, $margin_right, $margin_bottom);

        $gd_canvas = $this->createImage($canvas_width, $canvas_height, true, false, $fill_color);

        imagecopy($gd_canvas, $gd_image, 0, 0, 0, 0, $canvas_width, $canvas_height);

        foreach ($position_list as $positions) {
            imagecopy($gd_canvas, $gd_watermark, $positions[0], $positions[1], 0, 0, $watermark_width, $watermark_height);
        }

        imagecopymerge($gd_image, $gd_canvas, 0, 0, 0, 0, $canvas_width, $canvas_height, $watermark_alpha);

        imagedestroy($gd_watermark);
        imagedestroy($gd_canvas);

        unset($gd_watermark, $watermark_width, $watermark_height, $watermark_angle, $watermark_alpha, $layout, $margin_right, $margin_bottom, $fill_color, $position_list, $canvas_width, $canvas_height, $gd_canvas, $positions);
        return $gd_image;
    }

    /**
     * @param string $file
     *
     * @return string
     * @throws \Exception
     */
    protected function getImageMimeType(string $file): string
    {
        try {
            $image_type = exif_imagetype($file);

            if (false === $image_type) {
                throw new \Exception('libImage: Image type not supported!');
            }
        } catch (\Throwable) {
            throw new \Exception('libImage: Image type not supported!');
        }

        $mime_type = substr(image_type_to_mime_type($image_type), 6);

        unset($file, $image_type);
        return $mime_type;
    }

    /**
     * @param string $layout
     * @param int    $canvas_width
     * @param int    $canvas_height
     * @param int    $watermark_width
     * @param int    $watermark_height
     * @param int    $margin_right
     * @param int    $margin_bottom
     *
     * @return array
     */
    protected function getWatermarkPositions(string $layout, int $canvas_width, int $canvas_height, int $watermark_width, int $watermark_height, int $margin_right, int $margin_bottom): array
    {
        $position_list = [];

        switch ($layout) {
            case 'top-left':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.05),
                    (int)(($canvas_height - $watermark_height) * 0.05)
                ];
                break;

            case 'top-right':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.95),
                    (int)(($canvas_height - $watermark_height) * 0.05)
                ];
                break;

            case 'top-center':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.5),
                    (int)(($canvas_height - $watermark_height) * 0.05)
                ];
                break;

            case 'bottom-left':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.05),
                    (int)(($canvas_height - $watermark_height) * 0.95)
                ];
                break;

            case 'bottom-right':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.95),
                    (int)(($canvas_height - $watermark_height) * 0.95)
                ];
                break;

            case 'bottom-center':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.5),
                    (int)(($canvas_height - $watermark_height) * 0.95)
                ];
                break;

            case 'center':
                $position_list[] = [
                    (int)(($canvas_width - $watermark_width) * 0.5),
                    (int)(($canvas_height - $watermark_height) * 0.5)
                ];
                break;

            default:
                $margin_right  += $watermark_width;
                $margin_bottom += $watermark_height;

                $x_start  = (int)($watermark_width / 2);
                $y_start  = (int)($watermark_height / 2);
                $x_ending = $canvas_width + $margin_right;
                $y_ending = $canvas_height + $margin_bottom;

                for ($x = $x_start; $x < $x_ending; $x += $margin_right) {
                    for ($y = $y_start; $y < $y_ending; $y += $margin_bottom) {
                        $position_list[] = [$x, $y,];
                    }
                }

                unset($x_start, $y_start, $x_ending, $y_ending, $x, $y);
                break;
        }

        unset($layout, $canvas_width, $canvas_height, $watermark_width, $watermark_height, $margin_right, $margin_bottom);
        return $position_list;
    }
}