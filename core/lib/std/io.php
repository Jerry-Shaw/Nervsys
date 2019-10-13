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
class io
{

    //Base64 data header
    const BASE64 = 'data:text/argv;base64,';

    /**
     * Read URL CMD
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
        if (false === $from = strpos($_SERVER['REQUEST_URI'], '/', 1)) {
            return '';
        }

        if (false === $stop = strpos($_SERVER['REQUEST_URI'], '?')) {
            $stop = strlen($_SERVER['REQUEST_URI']);
        }

        $len = $stop - ++$from;

        return 1 < $len ? substr($_SERVER['REQUEST_URI'], $from, $len) : '';
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
     */
    public function read_input(): array
    {
        //Read raw data
        if ('' === $input = (string)file_get_contents('php://input')) {
            return [];
        }

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


    public function read_argv(): array
    {

    }

    public function read_pipe(): array
    {

    }


    /**
     * Encode data into base64 (url safe)
     *
     * @param string $string
     *
     * @return string
     */
    public function base64_url_encode(string $string): string
    {
        return strtr(rtrim(base64_encode($string), '='), '+/', '-_');
    }

    /**
     * Decode data from base64 (url safe)
     *
     * @param string $string
     *
     * @return string
     */
    public function base64_url_decode(string $string): string
    {
        $string   = strtr($string, '-_', '+/');
        $data_len = strlen($string);

        if (0 < $pad_len = $data_len % 4) {
            $string = str_pad($string, $data_len + $pad_len, '=', STR_PAD_RIGHT);
        }

        unset($data_len, $pad_len);
        return (string)base64_decode($string);
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
     * Build XML
     *
     * @param array $data
     * @param bool  $root
     * @param bool  $pretty
     *
     * @return string
     */
    public function build_xml(array $data, bool $root = true, bool $pretty = false): string
    {
        $xml = $end = '';

        if ($root && 1 < count($data)) {
            $xml .= '<xml>';
            $end = '</xml>';

            if ($pretty) {
                $xml .= PHP_EOL;
            }
        }

        foreach ($data as $key => $item) {
            if (is_numeric($key)) {
                $key = 'xml_' . $key;
            }

            $xml .= '<' . $key . '>';

            $xml .= is_array($item)
                ? self::build_xml($item, false, $pretty)
                : (!is_numeric($item) ? '<![CDATA[' . $item . ']]>' : $item);

            $xml .= '</' . $key . '>';

            if ($pretty) {
                $xml .= PHP_EOL;
            }
        }

        if ($root) {
            $xml .= $end;
        }

        unset($data, $root, $pretty, $end, $key, $item);
        return $xml;
    }
}