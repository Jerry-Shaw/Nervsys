<?php

/**
 * Output Parser
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

use core\system;

class output extends system
{
    //Pretty format
    private static $pretty = false;

    //Response header
    const HEADER = [
        //Output as JSON (default)
        'json' => 'Content-Type: application/json; charset=UTF-8',
        //Output as XML
        'xml'  => 'Content-Type: application/xml; charset=UTF-8',
        //Keep in pool for HTML
        'nul'  => 'Content-Type: text/html; charset=UTF-8'
    ];

    /**
     * Flush output content
     */
    public static function flush(): void
    {
        if (0 < parent::$err) {
            self::$pretty = true;
        }

        if (1 === count(parent::$result)) {
            parent::$result = reset(parent::$result);
        }

        if (!empty(parent::$error)) {
            parent::$result = parent::$error + ['data' => parent::$result];
        }

        $output = isset(self::HEADER[parent::$out]) ? parent::$out : 'json';

        if (!headers_sent()) {
            header(self::HEADER[$output]);
        }

        if ('nul' !== $output) {
            echo self::$output();

            if (parent::$is_cli) {
                echo PHP_EOL;
            }
        }

        if ('' !== parent::$logs) {
            echo PHP_EOL . PHP_EOL . parent::$logs;
        }

        unset($output);
    }

    /**
     * Output as JSON
     */
    private static function json(): string
    {
        return json_encode(parent::$result, self::$pretty ? 4034 : 3906);
    }

    /**
     * Output as XML
     *
     * @return string
     */
    private static function xml(): string
    {
        return data::build_xml(parent::$result, true, self::$pretty);
    }
}