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
        'xml'  => 'Content-Type: text/xml; charset=UTF-8',
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

        header(self::HEADER[$output = isset(self::HEADER[parent::$out]) ? parent::$out : 'json']);

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
        $xml = '<xml>';

        if (self::$pretty) {
            $xml .= PHP_EOL;
        }

        $xml .= is_array(parent::$result) ? self::build_xml(parent::$result) : '<![CDATA[' . parent::$result . ']]>';

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
        $xml  = '';
        $list = [];

        foreach ($data as $key => $item) {
            if (is_numeric($key)) {
                $key = 'name_' . $key;
            }

            $xml .= '<' . $key . '>';
            $xml .= is_array($item) ? self::build_xml($item) : (is_numeric($item) ? $item : '<![CDATA[' . $item . ']]>');
            $xml .= '</' . $key . '>';

            $list[] = $xml;
        }

        $xml = implode(self::$pretty ? PHP_EOL : '', $list);

        unset($data, $list, $key, $item);
        return $xml;
    }
}