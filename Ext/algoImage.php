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
    public function getSizeXY(): array
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
    public function getRGBA(int $x, int $y): array
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
    public function toIntensity(int $red, int $green, int $blue): int
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
    public function toGrayscale(int $red, int $green, int $blue): int
    {
        return round((1 - $this->toIntensity($red, $green, $blue) / 255) * 100);
    }

    /**
     * @param array $pixel_gray_data
     * @param bool  $by_percentage
     *
     * @return array
     */
    public function getHistogram(array $pixel_gray_data, bool $by_percentage = true): array
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
}