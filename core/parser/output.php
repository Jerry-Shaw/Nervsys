<?php

/**
 * Output Parser
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace core\parser;

use core\pool\process;
use core\pool\configure;

class output extends process
{
    //Output error
    public static $error = [];

    //JSON encode option
    public static $json_opt = 3906;

    /**
     * Output result in JSON
     */
    public static function json(): void
    {
        //Build result
        $count  = count(self::$result);
        $result = 1 === $count ? current(self::$result) : self::$result;

        //Build output
        $data = !empty(self::$error) ? self::$error + ['data' => &$result] : $result;
        $json = json_encode($data, self::$json_opt);

        echo !configure::$is_cgi ? $json . PHP_EOL : $json;
        unset($count, $result, $data, $json);
    }
}