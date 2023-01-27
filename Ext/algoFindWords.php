<?php

/**
 * Algorithm: Word finder
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

class algoFindWords extends Factory
{
    public int   $min_tf   = 2;
    public int   $step_len = 8;
    public float $min_diff = 0.4;

    public array $chunk_list = [];
    public array $word_tf    = [];
    public array $words      = [];

    /**
     * Set source document text and chunk size
     *
     * @param string $doc_text
     * @param int    $chunk_size
     *
     * @return $this
     */
    public function setDocText(string $doc_text, int $chunk_size = 10000): self
    {
        $this->chunk_list = mb_str_split($doc_text, $chunk_size, 'UTF-8');

        unset($doc_text, $chunk_size);
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
        foreach ($this->chunk_list as $text) {
            $this->word_tf = [];
            $this->find($text, mb_strlen($text, 'UTF-8'));
        }

        unset($text);
        return $this->words;
    }

    /**
     * Find all possible words
     *
     * @param string $text
     * @param int    $length
     */
    public function find(string $text, int $length): void
    {
        $last_tf = 1;

        $j = $length;
        $i = $length - $this->step_len;

        while ($j > 0) {
            if (0 > $i) {
                $i = 0;
            }

            $read_len  = $j - $i;
            $read_text = trim(mb_substr($text, $i, $read_len, 'UTF-8'));

            if ('' === $read_text) {
                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            if (1 === $read_len) {
                $this->words[] = $read_text;

                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            $now_tf = $this->getTf($text, $read_text);

            $tf_diff = ($now_tf - $last_tf) / $now_tf;

            if ($tf_diff >= $this->min_diff) {
                $this->words[] = $read_text;

                $last_tf = 1;

                $j = $i;
                $i = $j - $this->step_len;
                continue;
            }

            $last_tf = $now_tf;

            ++$i;
        }

        unset($text, $length, $last_tf, $j, $i, $read_len, $read_text, $now_tf, $tf_diff);
    }

    /**
     * Get TF value from source text
     *
     * @param string $text
     * @param string $gram
     *
     * @return int
     */
    private function getTf(string $text, string $gram): int
    {
        if (!isset($this->word_tf[$gram])) {
            $this->word_tf[$gram] = substr_count($text, $gram);
        }

        unset($text);
        return $this->word_tf[$gram];
    }
}