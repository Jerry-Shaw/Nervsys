<?php

/**
 * Algorithm: N-Gram
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Algo;

/**
 * Class Ngram
 *
 * @package Algo
 */
class Ngram
{
    public int   $src_len;
    public array $src_data;

    public array $dst_grams;

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setData(array $data): self
    {
        $this->src_data = &$data;
        $this->src_len  = count($this->src_data);

        unset($data);
        return $this;
    }

    /**
     * Get N Gram list
     *
     * @param int  $n
     * @param bool $fill_empty
     *
     * @return array
     */
    public function getGrams(int $n, bool $fill_empty = false): array
    {
        $grams = [];

        if ($fill_empty && $n > 1) {
            $this->fillEmpty($n - 1);
        }

        for ($i = 0; $i < $this->src_len; ++$i) {
            $grams[] = array_slice($this->src_data, $i, $n, true);
        }

        unset($n, $fill_empty, $i);
        return $grams;
    }

    /**
     * Fill empty elements to the beginning and end of the data
     *
     * @param int $count
     */
    private function fillEmpty(int $count): void
    {
        $fill_list = array_fill(0, $count, ' ');

        array_unshift($this->src_data, ...$fill_list);
        array_push($this->src_data, ...$fill_list);

        unset($count, $fill_list);
    }
}