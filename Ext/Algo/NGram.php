<?php

/**
 * Algorithm: N-Gram & Multilingual Tokenizer
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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

class NGram extends Factory
{
    private string $char_pattern = '';
    private array  $char_ranges  = [
        '\x{4E00}-\x{9FFF}',      # Han Basic
        '\x{3400}-\x{4DBF}',      # Han Ext A
        '\x{20000}-\x{2A6DF}',    # Han Ext B
        '\x{2A700}-\x{2B73F}',    # Han Ext C
        '\x{2B740}-\x{2B81F}',    # Han Ext D
        '\x{2B820}-\x{2CEAF}',    # Han Ext E
        '\x{2CEB0}-\x{2EBEF}',    # Han Ext F
        '\x{30000}-\x{3134F}',    # Han Ext G
        '\x{3040}-\x{309F}',      # Hiragana
        '\x{30A0}-\x{30FF}'       # Katakana
    ];

    /**
     * Adds a Unicode range to the no-space char list for text spliting.
     *
     * @param string $range Format: "\x{XXXX}-\x{YYYY}"
     *
     * @return $this
     */
    public function addCharRange(string $range): static
    {
        $this->char_ranges[] = $range;
        $this->char_pattern  = '';

        unset($range);
        return $this;
    }

    /**
     *  Split text into 'asian' and 'latin' groups based on script spacing.
     *
     * @param string $text UTF-8 text
     *
     * @return array{asian:string, latin:string}
     */
    public function splitText(string $text): array
    {
        if ('' === $text) {
            return ['asian' => '', 'latin' => ''];
        }

        $char_range = $this->buildRanges();

        $segments = preg_split(
            '/(' . $char_range . '+)/u',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if (false === $segments) {
            unset($char_range);
            return ['asian' => '', 'latin' => $text];
        }

        $asian_text    = [];
        $latin_text    = [];
        $is_asian_text = (bool)preg_match('/^' . $char_range . '+$/u', $segments[0]);

        foreach ($segments as $chunk) {
            if ($is_asian_text) {
                $asian_text[] = $chunk;
            } else {
                $latin_text[] = $chunk;
            }

            $is_asian_text = !$is_asian_text;
        }

        $result = [
            'asian' => implode(' ', $asian_text),
            'latin' => implode(' ', $latin_text),
        ];

        unset($text, $char_range, $segments, $asian_text, $latin_text, $is_asian_text, $chunk);
        return $result;
    }

    /**
     * Generates N-grams from a string.
     *
     * @param string $text       Input string
     * @param int    $n          Gram size (default 2)
     * @param bool   $fill_empty Whether to pad with $n-1 spaces at both ends
     *
     * @return string[]
     */
    public function getGrams(string $text, int $n = 2, bool $fill_empty = false): array
    {
        if ('' === $text || 1 > $n) {
            unset($text, $n, $fill_empty);
            return [];
        }

        if ($fill_empty && 1 < $n) {
            $pad  = str_repeat(' ', $n - 1);
            $text = $pad . $text . $pad;
            unset($pad);
        }

        $len = mb_strlen($text, 'UTF-8');

        if (1 === $n) {
            $result = mb_str_split($text, 1, 'UTF-8');
            unset($text, $n, $fill_empty, $len);
            return $result;
        }

        if ($len < $n) {
            unset($n, $fill_empty, $len);
            return [$text];
        }

        $grams = [];
        $limit = $len - $n;

        for ($i = 0; $i <= $limit; ++$i) {
            $grams[] = mb_substr($text, $i, $n, 'UTF-8');
        }

        unset($text, $n, $fill_empty, $len, $limit, $i);
        return $grams;
    }

    /**
     * @return string
     */
    private function buildRanges(): string
    {
        if ('' === $this->char_pattern) {
            $this->char_pattern = '[' . implode('', $this->char_ranges) . ']';
        }

        return $this->char_pattern;
    }
}