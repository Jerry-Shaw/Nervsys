<?php

/**
 * Misc function Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace ext;

class misc
{
    /**
     * Generate UUID (string hash based)
     *
     * @param string $string
     *
     * @return string
     */
    public static function uuid(string $string = ''): string
    {
        if ('' === $string) {
            //Create random string
            $string = uniqid(microtime() . getmypid() . mt_rand(), true);
        }

        $start  = 0;
        $codes  = [];
        $steps  = [8, 4, 4, 4, 12];
        $string = hash('md5', $string);

        foreach ($steps as $step) {
            $codes[] = substr($string, $start, $step);
            $start   += $step;
        }

        $uuid = implode('-', $codes);

        unset($string, $start, $codes, $steps, $step);
        return $uuid;
    }
}