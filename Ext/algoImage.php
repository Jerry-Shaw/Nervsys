<?php

/**
 * Algorithm: Image pixel processor
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
    public \GdImage $gdImage;

    /**
     * @param string $image_file_path
     *
     * @return $this
     */
    public function setImageFile(string $image_file_path): self
    {
        $this->gdImage = imagecreatefromstring(file_get_contents($image_file_path));

        unset($image_file_path);
        return $this;
    }

    /**
     * @return array
     */
    public function getImageSize(): array
    {
        return [
            'width'  => imagesx($this->gdImage),
            'height' => imagesy($this->gdImage)
        ];
    }

    /**
     * @param int $x
     * @param int $y
     *
     * @return array
     */
    public function getRGBAValues(int $x, int $y): array
    {
        return imagecolorsforindex($this->gdImage, imagecolorat($this->gdImage, $x, $y));
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     *
     * @return int
     */
    public function rgbToIntensity(int $red, int $green, int $blue): int
    {
        return ($red * 19595 + $green * 38469 + $blue * 7472) >> 16;
    }

    /**
     * @param int $red
     * @param int $green
     * @param int $blue
     *
     * @return int
     */
    public function rgbToGrayscale(int $red, int $green, int $blue): int
    {
        return round((1 - $this->rgbToIntensity($red, $green, $blue) / 0xFF) * 100);
    }

    /**
     * @param array $pixel_gray_data
     * @param bool  $by_percentage
     *
     * @return array
     */
    public function getGrayHistogramData(array $pixel_gray_data, bool $by_percentage = true): array
    {
        $data = [];

        for ($i = 0; $i <= 0xFF; ++$i) {
            $data[$i] = 0;
        }

        $total_pixels     = count($pixel_gray_data);
        $gray_value_count = array_count_values($pixel_gray_data);

        foreach ($gray_value_count as $value => $count) {
            $data[$value] = $by_percentage ? round(100 * $count / $total_pixels, 4) : $count;
        }

        unset($pixel_gray_data, $by_percentage, $i, $total_pixels, $gray_value_count, $value, $count);
        return $data;
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