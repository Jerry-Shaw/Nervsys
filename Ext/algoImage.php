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

    public int $image_width;
    public int $image_height;

    /**
     * @param string $image_file_path
     *
     * @return $this
     */
    public function setImageFile(string $image_file_path): self
    {
        $this->gdImage = imagecreatefromstring(file_get_contents($image_file_path));

        $this->image_width  = imagesx($this->gdImage);
        $this->image_height = imagesy($this->gdImage);

        unset($image_file_path);
        return $this;
    }

    /**
     * @return array
     */
    public function getPixelArray(): array
    {
        $pixels = [];

        for ($y = 0; $y < $this->image_height; ++$y) {
            for ($x = 0; $x < $this->image_width; ++$x) {
                $rgb = imagecolorat($this->gdImage, $x, $y);

                $red   = ($rgb >> 16) & 255;
                $green = ($rgb >> 8) & 255;
                $blue  = $rgb & 255;

                $luma = ($red * 19595 + $green * 38469 + $blue * 7472) >> 16;
                $gray = round((1 - $luma / 255) * 100);

                $pixels[] = [
                    'x'     => $x,
                    'y'     => $y,
                    'red'   => $red,
                    'green' => $green,
                    'blue'  => $blue,
                    'luma'  => $luma,
                    'gray'  => $gray,
                ];
            }
        }

        unset($y, $x, $rgb, $red, $green, $blue, $luma, $gray);
        return $pixels;
    }
}