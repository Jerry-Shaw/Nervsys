<?php

/**
 * Algorithm: CLAHE algorithm
 *
 * Copyright 2026 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext\Algo;

use Nervsys\Core\Factory;

class CLAHE extends Factory
{
    private array $color_cache = [];

    /**
     * @param \GdImage $gd_image
     * @param int      $tile_size
     * @param float    $clip_limit
     *
     * @return \GdImage
     */
    public function processGd(\GdImage $gd_image, int $tile_size, float $clip_limit): \GdImage
    {
        $image_width  = imagesx($gd_image);
        $image_height = imagesy($gd_image);

        if (0 >= $image_width || 0 >= $image_height) {
            throw new \InvalidArgumentException('Invalid image dimensions: ' . $image_width . 'x' . $image_height);
        }

        $buffer = $this->gdToBuffer($gd_image, $image_width, $image_height);
        $buffer = $this->runClahe($buffer, $tile_size, $clip_limit);
        $result = $this->bufferToGD($buffer, $image_width, $image_height);

        unset($gd_image, $tile_size, $clip_limit, $image_width, $image_height, $buffer);
        $this->color_cache = [];
        return $result;
    }

    /**
     * @param array $pixel_matrix
     * @param int   $tile_size
     * @param float $clip_limit
     *
     * @return array
     */
    public function processBuffer(array $pixel_matrix, int $tile_size, float $clip_limit): array
    {
        $buffer = $this->runClahe($pixel_matrix, $tile_size, $clip_limit);

        $result = [
            'pixel_matrix' => $buffer,
            'tile_size'    => $tile_size,
            'clip_limit'   => $clip_limit,
        ];

        unset($pixel_matrix, $tile_size, $clip_limit, $buffer);
        $this->color_cache = [];
        return $result;
    }

    /**
     * @param array $buffer
     * @param int   $tile_size
     * @param float $clip_limit
     *
     * @return array
     */
    private function runClahe(array $buffer, int $tile_size, float $clip_limit): array
    {
        $image_height = count($buffer);
        $image_width  = count($buffer[0]);

        $tile_rows = (int)ceil($image_height / $tile_size);
        $tile_cols = (int)ceil($image_width / $tile_size);

        $tile_map = [];
        for ($ty = 0; $ty < $tile_rows; ++$ty) {
            for ($tx = 0; $tx < $tile_cols; ++$tx) {
                $y1 = $ty * $tile_size;
                $x1 = $tx * $tile_size;

                $y2 = min($image_height, $y1 + $tile_size);
                $x2 = min($image_width, $x1 + $tile_size);

                if ($y2 <= $y1 || $x2 <= $x1) {
                    continue;
                }

                $tile_data = [];
                for ($row = $y1; $row < $y2; ++$row) {
                    $tile_data[] = array_slice($buffer[$row], $x1, $x2 - $x1);
                }

                $tile_map[$ty][$tx] = $this->processTile($tile_data, $clip_limit);
            }
        }

        $result = $this->mergeTiles($buffer, $tile_map, $tile_size, $image_height, $image_width, $tile_rows, $tile_cols);

        unset($buffer, $tile_size, $clip_limit, $image_height, $image_width, $tile_rows, $tile_cols, $tile_map, $ty, $tx, $y1, $x1, $y2, $x2, $tile_data, $row);
        return $result;
    }

    /**
     * @param array $original
     * @param array $tile_map
     * @param int   $tile_size
     * @param int   $image_height
     * @param int   $image_width
     * @param int   $tile_rows
     * @param int   $tile_cols
     *
     * @return array
     */
    private function mergeTiles(array $original, array $tile_map, int $tile_size, int $image_height, int $image_width, int $tile_rows, int $tile_cols): array
    {
        $result = array_fill(0, $image_height, array_fill(0, $image_width, 0));

        for ($r = 0; $r < $image_height; ++$r) {
            for ($c = 0; $c < $image_width; ++$c) {
                $ty = $r / $tile_size;
                $tx = $c / $tile_size;

                $ty0 = (int)floor($ty);
                $tx0 = (int)floor($tx);
                $dy  = $ty - $ty0;
                $dx  = $tx - $tx0;

                $weights = [
                    [$ty0, $tx0, (1 - $dx) * (1 - $dy)],
                    [$ty0, $tx0 + 1, $dx * (1 - $dy)],
                    [$ty0 + 1, $tx0, (1 - $dx) * $dy],
                    [$ty0 + 1, $tx0 + 1, $dx * $dy]
                ];

                $value        = 0;
                $total_weight = 0;

                foreach ($weights as [$ty_i, $tx_i, $weight]) {
                    if (0 <= $ty_i && $ty_i < $tile_rows && 0 <= $tx_i && $tx_i < $tile_cols) {
                        $tile_y_start = $ty_i * $tile_size;
                        $tile_x_start = $tx_i * $tile_size;

                        $tile_height = min($image_height - $tile_y_start, $tile_size);
                        $tile_width  = min($image_width - $tile_x_start, $tile_size);

                        $local_r = $r - $tile_y_start;
                        $local_c = $c - $tile_x_start;

                        if (0 <= $local_r && $local_r < $tile_height && 0 <= $local_c && $local_c < $tile_width) {
                            $value        += $tile_map[$ty_i][$tx_i][$local_r][$local_c] * $weight;
                            $total_weight += $weight;
                        }
                    }
                }

                $result[$r][$c] = 0 < $total_weight
                    ? (int)round($value / $total_weight)
                    : $original[$r][$c];
            }
        }

        unset($original, $tile_map, $tile_size, $image_height, $image_width, $tile_rows, $tile_cols, $r, $c, $ty, $tx, $ty0, $tx0, $dy, $dx, $weights, $value, $total_weight, $ty_i, $tx_i, $weight, $tile_y_start, $tile_x_start, $tile_height, $tile_width, $local_r, $local_c);
        return $result;
    }

    /**
     * @param array $image_data
     * @param float $clip_limit
     *
     * @return array
     */
    private function processTile(array $image_data, float $clip_limit): array
    {
        $tile_height = count($image_data);
        if (0 === $tile_height) {
            return [];
        }

        $tile_width = count($image_data[0]);
        if (0 === $tile_width) {
            return [];
        }

        $histogram = array_fill(0, 256, 0);
        foreach ($image_data as $row) {
            foreach ($row as $value) {
                $value = max(0, min(255, (int)$value));
                ++$histogram[$value];
            }
        }

        $tile_pixels = max(1, $tile_height * $tile_width);
        $threshold   = max(1, (int)ceil($clip_limit * $tile_pixels / 256));

        $excess_pixels = 0;
        foreach ($histogram as $bin => $count) {
            if ($count > $threshold) {
                $excess_pixels   += $count - $threshold;
                $histogram[$bin] = $threshold;
            }
        }

        if (0 < $excess_pixels) {
            $this->handleExcess($histogram, $excess_pixels);
        }

        $cdf     = [];
        $cdf_sum = 0;

        for ($i = 0; $i < 256; ++$i) {
            $cdf_sum += $histogram[$i];
            $cdf[$i] = $cdf_sum;
        }

        $cdf_min = -1;
        for ($i = 0; $i < 256; ++$i) {
            if (0 < $cdf[$i]) {
                $cdf_min = $cdf[$i];
                break;
            }
        }

        if (0 === $cdf_sum || -1 === $cdf_min || $cdf_sum <= $cdf_min) {
            $lut = range(0, 255);
        } else {
            $lut = $this->buildClaheLut($cdf, $cdf_sum, $cdf_min);
        }

        $tile = [];
        foreach ($image_data as $row_idx => $row) {
            $row_value = [];

            foreach ($row as $value) {
                $row_value[] = $lut[$value];
            }

            $tile[] = $row_value;
        }

        unset($image_data, $clip_limit, $tile_height, $tile_width, $histogram, $row, $value, $tile_pixels, $threshold, $excess_pixels, $bin, $count, $cdf, $cdf_sum, $i, $cdf_min, $lut, $row_value, $row_idx);
        return $tile;
    }

    /**
     * @param array $cdf
     * @param int   $cdf_sum
     * @param int   $cdf_min
     *
     * @return array
     */
    private function buildClaheLut(array $cdf, int $cdf_sum, int $cdf_min): array
    {
        if (-1 === $cdf_min || 0 === ($cdf_sum - $cdf_min)) {
            return range(0, 255);
        }

        $lut = [];
        for ($i = 0; $i < 256; ++$i) {
            $lut_val = max(0, min(255, (int)round((($cdf[$i] - $cdf_min) * 255.0) / ($cdf_sum - $cdf_min))));
            $lut[$i] = $lut_val;
        }

        unset($cdf, $cdf_sum, $cdf_min, $i, $lut_val);
        return $lut;
    }

    /**
     * @param int      $pixel_color
     * @param bool     $true_color
     * @param \GdImage $gd_image
     *
     * @return int
     */
    private function getGrayValue(int $pixel_color, bool $true_color, \GdImage $gd_image): int
    {
        if ($true_color) {
            $red   = ($pixel_color >> 16) & 0xFF;
            $green = ($pixel_color >> 8) & 0xFF;
            $blue  = $pixel_color & 0xFF;
        } else {
            $rgb   = imagecolorsforindex($gd_image, $pixel_color);
            $red   = $rgb['red'];
            $green = $rgb['green'];
            $blue  = $rgb['blue'];

            unset($rgb);
        }

        $gray_value = (int)round($red * 0.299 + $green * 0.587 + $blue * 0.114);

        unset($pixel_color, $true_color, $gd_image, $red, $green, $blue);
        return $gray_value;
    }

    /**
     * @param array $histogram
     * @param int   $excess_pixels
     *
     * @return void
     */
    private function handleExcess(array &$histogram, int $excess_pixels): void
    {
        $bin_share_value  = (int)floor($excess_pixels / 256);
        $remaining_pixels = $excess_pixels % 256;

        for ($bin_index = 0; $bin_index < 256; ++$bin_index) {
            $histogram[$bin_index] += $bin_share_value;
        }

        for ($bin_index = 0; $bin_index < $remaining_pixels; ++$bin_index) {
            ++$histogram[$bin_index];
        }

        unset($excess_pixels, $bin_share_value, $remaining_pixels, $bin_index);
    }

    /**
     * @param \GdImage $gd_image
     * @param int      $image_width
     * @param int      $image_height
     *
     * @return array
     */
    private function gdToBuffer(\GdImage $gd_image, int $image_width, int $image_height): array
    {
        $buffer     = [];
        $true_color = imageistruecolor($gd_image);

        for ($row_idx = 0; $row_idx < $image_height; ++$row_idx) {
            $row_values = [];

            for ($col_idx = 0; $col_idx < $image_width; ++$col_idx) {
                $pixel_color = imagecolorat($gd_image, $col_idx, $row_idx);
                $gray_value  = is_int($pixel_color)
                    ? $this->getGrayValue($pixel_color, $true_color, $gd_image)
                    : 128;

                $row_values[] = $gray_value;
            }

            $buffer[] = $row_values;
        }

        unset($gd_image, $image_width, $image_height, $true_color, $row_idx, $row_values, $col_idx, $pixel_color, $gray_value);
        return $buffer;
    }

    /**
     * @param array $buffer
     * @param int   $image_width
     * @param int   $image_height
     *
     * @return \GdImage
     */
    private function bufferToGD(array $buffer, int $image_width, int $image_height): \GdImage
    {
        $this->color_cache = [];

        $gd_image = imagecreatetruecolor($image_width, $image_height);

        for ($row_idx = 0; $row_idx < $image_height; ++$row_idx) {
            if (!isset($buffer[$row_idx])) {
                continue;
            }

            foreach ($buffer[$row_idx] as $col_idx => $gray_value) {
                if (!isset($this->color_cache[$gray_value])) {
                    $this->color_cache[$gray_value] = imagecolorallocate($gd_image, $gray_value, $gray_value, $gray_value);
                }

                imagesetpixel($gd_image, $col_idx, $row_idx, $this->color_cache[$gray_value]);
            }
        }

        unset($buffer, $image_width, $image_height, $row_idx, $col_idx, $gray_value);
        return $gd_image;
    }
}