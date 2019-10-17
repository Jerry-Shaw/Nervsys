<?php

/**
 * NS System IO controller
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

namespace core\lib\std;

/**
 * Class io
 *
 * @package core\lib\std
 */
final class io
{
    //Base64 data header
    const BASE64 = 'data:text/argv;base64,';

    /**
     * Read CMD from URL
     *
     * @return string
     */
    public function read_url(): string
    {
        //Read from PATH_INFO
        if (isset($_SERVER['PATH_INFO']) && 1 < strlen($_SERVER['PATH_INFO'])) {
            return substr($_SERVER['PATH_INFO'], 1);
        }

        //Read from REQUEST_URI
        if (false !== $start = strpos($_SERVER['REQUEST_URI'], '/', 1)) {
            $stop = strpos($_SERVER['REQUEST_URI'], '?');

            if (0 < $len = (false === $stop ? strlen($_SERVER['REQUEST_URI']) : $stop) - ++$start) {
                return substr($_SERVER['REQUEST_URI'], $start, $len);
            }
        }

        //CMD NOT found
        return '';
    }

    /**
     * Read HTTP data
     */
    public function read_http(): array
    {
        return $_FILES + $_POST + $_GET;
    }

    /**
     * Read input data
     *
     * @param string $input
     *
     * @return array
     */
    public function read_input(string $input): array
    {
        //Decode data in JSON
        if (is_array($data = json_decode($input, true))) {
            unset($input);
            return $data;
        }

        //Decode data in XML
        libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($input);
        $data = false !== $xml ? (array)$xml : [];
        libxml_clear_errors();

        unset($input, $xml);
        return $data;
    }

    /**
     * Read CLI arguments
     *
     * @return array
     */
    public function read_argv(): array
    {
        /**
         * CLI options
         *
         * c: Commands
         * d: Data package (Pass to CGI script)
         * p: Pipe data package (Pass to CLI program)
         * r: Return type (json/xml, default: none, no output)
         */
        $opt = getopt('c:d:p:r:', [], $optind);

        //Default data
        $data = ['c' => '', 'r' => '', 'p' => '', 'a' => [], 'd' => []];

        //Read argv data
        $data['a'] = array_slice($_SERVER['argv'], $optind);

        //Decode data package
        if (isset($opt['d'])) {
            $data_text = $this->decode($opt['d']);
            $data['d'] = $this->read_input($data_text);

            if (empty($data['d'])) {
                parse_str($data_text, $data['d']);
            }

            unset($opt['d'], $data_text);
        }

        //Decode pipe data
        if (isset($opt['p'])) {
            $data['p'] = $this->decode($opt['p']);
            unset($opt['p']);
        }

        //Copy other option values
        foreach ($opt as $key => $value) {
            $data[$key] = $value;
        }

        //Command NOT found
        if ('' === $data['c']) {
            //Move first argv to command
            $data['c'] = array_shift($data['a']) ?? '';
        }

        unset($opt, $optind, $key, $value);
        return $data;
    }

    /**
     * Encode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public function encode(string $value): string
    {
        return self::BASE64 . base64_encode($value);
    }

    /**
     * Decode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public function decode(string $value): string
    {
        if (0 === strpos($value, self::BASE64)) {
            $value = substr($value, strlen(self::BASE64));
            $value = base64_decode($value);
        }

        return $value;
    }

    /**
     * Build JSON
     *
     * @param array $data
     *
     * @return string
     */
    public function build_json(array $data): string
    {
        return json_encode($data, JSON_FORMAT);
    }

    /**
     * Build XML
     *
     * @param array $data
     * @param bool  $root
     *
     * @return string
     */
    public function build_xml(array $data, bool $root = true): string
    {
        $xml = $end = '';

        if ($root && 1 < count($data)) {
            $xml .= '<xml>';
            $end = '</xml>';
        }

        foreach ($data as $key => $item) {
            if (is_numeric($key)) {
                $key = 'xml_' . $key;
            }

            $xml .= '<' . $key . '>';

            $xml .= is_array($item)
                ? self::build_xml($item, false)
                : (!is_numeric($item) ? '<![CDATA[' . $item . ']]>' : $item);

            $xml .= '</' . $key . '>';
        }

        if ($root) {
            $xml .= $end;
        }

        unset($data, $root, $end, $key, $item);
        return $xml;
    }
}