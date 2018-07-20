<?php

/**
 * Output Parser
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
 * Copyright 2018 空城 <694623056@qq.com>
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
use core\pool\setting;

class output extends process
{
    //Error
    public static $error = [];

    //Method
    public static $method = 'json';

    //Pretty format
    private static $pretty = false;

    //Response header
    private static $header = [
        'json' => 'Content-Type: application/json; charset=utf-8',
        'html' => 'Content-Type: text/html; charset=utf-8',
        'xml'  => 'Content-Type: text/xml; charset=utf-8',
    ];

    /**
     * Flush output content
     */
    public static function flush(): void
    {
        if ('html' !== $method = isset(self::$header[self::$method]) ? self::$method : 'json') {
            if (1 === count(self::$result)) {
                self::$result = reset(self::$result);
            }

            if (!empty(self::$error)) {
                self::$result = self::$error + ['data' => self::$result];
            }
        }

        if (0 < error_reporting()) {
            self::$pretty = true;
        }

        header(self::$header[$method]);
        echo self::$method();

        if (setting::$is_cli) {
            echo PHP_EOL;
        }

        unset($method);
    }

    /**
     * Output as JSON
     */
    private static function json(): string
    {
        return json_encode(self::$result, self::$pretty ? 4034 : 3906);
    }

    /**
     * Output as HTML
     *
     * @return string
     */
    private static function html(): string
    {
        return is_string(self::$result) ? self::$result : (string)reset(self::$result);
    }

    /**
     * Output as XML
     *
     * @return string
     */
    private static function xml(): string
    {
        $xml = '<xml>';

        if (self::$pretty) {
            $xml .= PHP_EOL;
        }

        $xml .= self::build_xml(self::$result);

        if (self::$pretty) {
            $xml .= PHP_EOL;
        }

        $xml .= '</xml>';

        return $xml;
    }

    /**
     * Build XML
     *
     * @param array $data
     *
     * @return string
     */
    private static function build_xml(array $data): string
    {
        $xml = '';

        foreach ($data as $key => $item) {
            $xml .= '<' . $key . '>';
            $xml .= is_array($item) ? self::build_xml($item) : (is_numeric($item) ? $item : '<![CDATA[' . $item . ']]>');
            $xml .= '</' . $key . '>';

            if (self::$pretty) {
                $xml .= PHP_EOL;
            }
        }

        unset($data, $key, $item);
        return $xml;
    }
}