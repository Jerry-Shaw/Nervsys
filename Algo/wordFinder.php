<?php

/**
 * Algorithm: Word finder
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
 * Class wordFinder
 *
 * @package Algo
 */
class wordFinder extends Factory
{
    public int   $min_tf   = 2;
    public int   $step_len = 8;
    public float $min_diff = 0.8;

    public int    $src_len  = 0;
    public string $src_text = '';

    public array $word_tf = [];

    /**
     * Set source document list
     *
     * @param array $doc_list
     *
     * @return $this
     */
    public function setDocs(array $doc_list): self
    {
        $this->src_text = implode(' ', $doc_list);
        $this->src_len  = mb_strlen($this->src_text, 'UTF-8');

        unset($doc_list);
        return $this;
    }

    /**
     * Set finder variables
     *
     * @param int   $min_tf
     * @param float $min_diff
     * @param int   $step_len
     *
     * @return $this
     */
    public function setVars(int $min_tf, float $min_diff, int $step_len = 8): self
    {
        $this->min_tf   = &$min_tf;
        $this->min_diff = &$min_diff;
        $this->step_len = &$step_len;

        unset($min_tf, $min_diff, $step_len);
        return $this;
    }

    /**
     * Get all words with TF increased larger than min_diff
     *
     * @return array
     */
    public function getWords(): array
    {
        $words = [];

        $last_wd = '';
        $last_tf = 1;

        $j = $this->src_len;
        $i = $this->src_len - $this->step_len;

        while ($j > 1) {
            if (0 > $i) {
                $i = 0;
            }

            $read_len  = $j - $i;
            $read_text = trim(mb_substr($this->src_text, $i, $read_len, 'UTF-8'));

            if ('' === $read_text || $read_text === $last_wd) {
                $last_wd = '';
                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            $now_tf = $this->getTf($read_text);

            if (1 === $read_len) {
                $words[] = $read_text;

                $last_wd = '';
                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            $tf_diff = ($now_tf - $last_tf) / $now_tf;

            if ($tf_diff >= $this->min_diff) {
                $words[] = $read_text;

                $last_wd = '';
                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            $last_wd = $read_text;
            $last_tf = $now_tf;

            ++$i;
        }

        unset($last_wd, $last_tf, $j, $i, $read_len, $read_text, $now_tf, $tf_diff);
        return $words;
    }

    /**
     * Get TF value from source text
     *
     * @param string $gram
     *
     * @return int
     */
    private function getTf(string $gram): int
    {
        if (!isset($this->word_tf[$gram])) {
            $this->word_tf[$gram] = substr_count($this->src_text, $gram);
        }

        return $this->word_tf[$gram];
    }
}