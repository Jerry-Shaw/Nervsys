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
        return round((1 - $this->rgbToIntensity($red, $green, $blue) / 255) * 100);
    }

    /**
     * @param array $pixel_gray_data
     * @param bool  $by_percentage
     *
     * @return array
     */
    public function getHistogramData(array $pixel_gray_data, bool $by_percentage = true): array
    {
        $data = [];

        for ($i = 0; $i < 256; ++$i) {
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
     * @param array $histogram_data
     * @param int   $grayscale_value
     *
     * @return float
     */
    public function getVarianceByGrayscale(array $histogram_data, int $grayscale_value): float
    {
        $w0_pct  = $w1_pct = 0;
        $u0_data = $u1_data = 0;

        foreach ($histogram_data as $gray_value => $percent_value) {
            if ($gray_value < $grayscale_value) {
                $w0_pct  += $percent_value;
                $u0_data += $gray_value * $percent_value;
            } else {
                $w1_pct  += $percent_value;
                $u1_data += $gray_value * $percent_value;
            }
        }

        $u  = $u0_data + $u1_data;
        $u0 = $u0_data / ($w0_pct ?: 1);
        $u1 = $u1_data / ($w1_pct ?: 1);

        $f = $w0_pct * pow($u0 - $u, 2) + $w1_pct * pow($u1 - $u, 2);

        unset($histogram_data, $grayscale_value, $w0_pct, $w1_pct, $u0_data, $u1_data, $gray_value, $percent_value, $u, $u0, $u1);
        return $f;
    }
}