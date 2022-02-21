<?php

/**
 * Algorithm: Peak finder
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
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

use Nervsys\LC\Factory;

class algoFindPeaks extends Factory
{
    public array $keys;
    public array $values;
    public array $vector;

    public array $diff_trend = [];

    /**
     * Set data points
     *
     * @param array $points
     *
     * @return $this
     */
    public function setData(array $points): self
    {
        $this->keys   = array_keys($points);
        $this->values = array_values($points);
        $this->vector = $this->getVct($this->values);
        $vector_count = count($this->vector) - 1;

        $this->calcTrend($vector_count)->getDiffTrend($vector_count);

        unset($points);
        return $this;
    }

    /**
     * Find high peak points
     *
     * @return array
     */
    public function findHighPeaks(): array
    {
        return $this->fetchPoints(-2);
    }

    /**
     * Find low peak points
     *
     * @return array
     */
    public function findLowPeaks(): array
    {
        return $this->fetchPoints(2);
    }

    /**
     * fetch points matched given vector diff value
     *
     * @param int $diff_val
     *
     * @return array
     */
    public function fetchPoints(int $diff_val): array
    {
        $keys = array_keys($this->diff_trend, $diff_val, true);

        if (empty($keys)) {
            return [];
        }

        $points = [];

        foreach ($keys as $key) {
            $points[$this->keys[$key]] = $this->values[$key];
        }

        unset($diff_val, $keys, $key);
        return $points;
    }

    /**
     * Calculate data vector
     *
     * @param array $data
     *
     * @return array
     */
    private function getVct(array $data): array
    {
        $vct = [];
        $len = count($data) - 1;

        for ($i = 1; $i <= $len; ++$i) {
            $vct[] = $data[$i] <=> $data[$i - 1];
        }

        unset($data, $len, $i);
        return $vct;
    }

    /**
     * Calculate vector trend
     *
     * @param int $len
     *
     * @return $this
     */
    private function calcTrend(int $len): self
    {
        for ($i = $len; $i >= 0; --$i) {
            if (0 !== $this->vector[$i]) {
                continue;
            }

            if ($i === $len) {
                $this->vector[$i] = 1;
            } elseif (0 <= $this->vector[$i + 1]) {
                $this->vector[$i] = 1;
            } else {
                $this->vector[$i] = -1;
            }
        }

        unset($len, $i);
        return $this;
    }

    /**
     * Get diff trend of data points
     *
     * @param int $len
     */
    private function getDiffTrend(int $len): void
    {
        $this->diff_trend = [];

        for ($i = 1; $i <= $len; ++$i) {
            $this->diff_trend[$i] = $this->vector[$i] - $this->vector[$i - 1];
        }

        unset($len, $i);
    }
}