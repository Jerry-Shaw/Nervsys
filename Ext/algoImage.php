<?php

/**
 * Algorithm: Image data algorithm
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

class algoImage extends Factory
{
    /**
     * @param \GdImage $gd_image
     *
     * @return array
     */
    public function getImageSize(\GdImage $gd_image): array
    {
        $size_xy = [
            'width'  => imagesx($gd_image),
            'height' => imagesy($gd_image)
        ];

        unset($gd_image);
        return $size_xy;
    }

    /**
     * @param \GdImage $gd_image
     * @param int      $x
     * @param int      $y
     *
     * @return array
     */
    public function getRGBAValues(\GdImage $gd_image, int $x, int $y): array
    {
        $rgba = imagecolorsforindex($gd_image, imagecolorat($gd_image, $x, $y));

        unset($gd_image, $x, $y);
        return $rgba;
    }

    /**
     * @param int  $red
     * @param int  $green
     * @param int  $blue
     * @param bool $normalize
     *
     * @return int
     */
    public function rgbToGrayValue(int $red, int $green, int $blue, bool $normalize = false): int
    {
        $gray_value = ($red * 19595 + $green * 38469 + $blue * 7472) >> 16;

        if ($normalize) {
            $gray_value = (int)round(($gray_value / 0xFF) * 100);
        }

        unset($red, $green, $blue, $normalize);
        return $gray_value;
    }

    /**
     * @param \GdImage $gd_image
     * @param bool     $by_percentage
     *
     * @return array
     */
    public function getGrayHistogram(\GdImage $gd_image, bool $by_percentage = true): array
    {
        $width  = imagesx($gd_image);
        $height = imagesy($gd_image);

        $gray_values = $gray_histogram = [];

        for ($i = 0; $i <= 0xFF; ++$i) {
            $gray_histogram[$i] = 0;
        }

        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $rgba_value    = $this->getRGBAValues($gd_image, $x, $y);
                $gray_values[] = $this->rgbToGrayValue($rgba_value['red'], $rgba_value['green'], $rgba_value['blue'],);
            }
        }

        $total_pixels     = count($gray_values);
        $gray_value_count = array_count_values($gray_values);

        foreach ($gray_value_count as $value => $count) {
            $gray_histogram[$value] = $by_percentage ? round(100 * $count / $total_pixels, 4) : $count;
        }

        unset($gd_image, $by_percentage, $width, $height, $gray_values, $i, $x, $y, $rgba_value, $total_pixels, $gray_value_count, $value, $count);
        return $gray_histogram;
    }

    /**
     * @param array $gray_histogram
     *
     * @return float
     */
    public function getThresholdByOTSU(array $gray_histogram): float
    {
        $init_val  = -1;
        $threshold = 128;
        $total_num = (int)array_sum($gray_histogram);

        for ($i = 1; $i <= 0xFF; ++$i) {
            $bg_gray = $fg_gray = 0;

            $bg_data = array_slice($gray_histogram, 0, $i, true);
            $fg_data = array_slice($gray_histogram, $i, 0xFF, true);

            $bg_sum = array_sum($bg_data);
            $fg_sum = array_sum($fg_data);

            foreach ($bg_data as $gray_val => $pixel_num) {
                $bg_gray += $gray_val * $pixel_num;
            }

            foreach ($fg_data as $gray_val => $pixel_num) {
                $fg_gray += $gray_val * $pixel_num;
            }

            $bg_pct = $bg_sum / $total_num;
            $fg_pct = $fg_sum / $total_num;
            $bg_avg = $bg_sum > 0 ? $bg_gray / $bg_sum : 0;
            $fg_avg = $fg_sum > 0 ? $fg_gray / $fg_sum : 0;

            $gray_f = $fg_pct * $bg_pct * pow($fg_avg - $bg_avg, 2);

            if ($gray_f > $init_val) {
                $init_val  = $gray_f;
                $threshold = $i;
            }
        }

        unset($gray_histogram, $init_val, $total_num, $i, $bg_gray, $fg_gray, $bg_data, $fg_data, $bg_sum, $fg_sum, $gray_val, $pixel_num, $bg_avg, $bg_pct, $fg_avg, $fg_pct, $gray_f);
        return $threshold;
    }
}