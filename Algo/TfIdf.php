<?php

/**
 * Algorithm: TF-IFD
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

use Core\Factory;

/**
 * Class TfIdf
 *
 * @package Algo
 */
class TfIdf extends Factory
{
    public array $src_grams = [];
    public array $src_docs  = [];
    public int   $src_len   = 0;

    public array $dst_tf  = [];
    public array $dst_idf = [];

    /**
     * Set source data (exp: N-Gram/findWords results, source documents in list)
     *
     * @param array $gram_list
     * @param array $doc_list
     *
     * @return $this
     */
    public function setSrcData(array $gram_list, array $doc_list): self
    {
        $this->src_grams = &$gram_list;
        $this->src_docs  = &$doc_list;
        $this->src_len   = count($doc_list);

        unset($gram_list, $doc_list);
        return $this;
    }

    /**
     * Get gram's TF value
     *
     * @param string $gram
     *
     * @return int
     */
    public function getTf(string $gram): int
    {
        if (empty($this->dst_tf)) {
            $this->dst_tf = array_count_values($this->src_grams);
        }

        return $this->dst_tf[$gram] ?? 0;
    }

    /**
     * Get gram's IDF value
     *
     * @param string $gram
     *
     * @return float
     */
    public function getIdf(string $gram): float
    {
        if (!isset($this->dst_idf[$gram])) {
            $this->dst_idf[$gram] = log($this->src_len / ($this->findContains($gram) + 1));
        }

        return $this->dst_idf[$gram] ?? 0;
    }

    /**
     * Find gram's contained quantity in all documents
     *
     * @param string $gram
     *
     * @return int
     */
    private function findContains(string $gram): int
    {
        $count = 0;

        foreach ($this->src_docs as $doc) {
            if (false !== strpos($doc, $gram)) {
                ++$count;
            }
        }

        unset($gram, $doc);
        return $count;
    }
}