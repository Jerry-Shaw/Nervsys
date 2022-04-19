<?php

/**
 * I/O Data Parser library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Lib;

use Nervsys\LC\Factory;

class IOData extends Factory
{
    public array $cgi_handler    = [];
    public array $cli_handler    = [];
    public array $output_handler = [];

    public array $header_keys = [];
    public array $cookie_keys = [];

    public string $src_cmd  = '';
    public string $cwd_path = '';
    public array  $src_argv = [];

    public array $src_msg    = [];
    public array $src_input  = [];
    public array $src_output = [];

    public string $content_type = '';

    protected string $base64_marker  = 'data:text/argv;base64,';
    protected array  $response_types = ['application/json', 'application/xml', 'text/plain', 'text/html', 'text/none'];

    /**
     * @return void
     */
    public function readCgi(): void
    {
        $this->readAccept();

        $this->src_cmd = trim($this->readUrl());

        $this->src_input = $this->readHttp();
        $this->src_input += $this->readInput(file_get_contents('php://input'));

        if (!empty($this->header_keys)) {
            $this->src_input = array_diff_key($this->src_input, array_flip($this->header_keys));
            $this->src_input += $this->readHeader();
        }

        if (!empty($this->cookie_keys)) {
            $this->src_input = array_diff_key($this->src_input, array_flip($this->cookie_keys));
            $this->src_input += $this->readCookie();
        }

        if ('' === $this->src_cmd && isset($this->src_input['c'])) {
            $this->src_cmd = trim($this->src_input['c']);
            unset($this->src_input['c']);
        }

        foreach ($this->cgi_handler as $handler) {
            call_user_func($handler, $this);
        }

        unset($handler);
    }

    /**
     * Read arguments (CLI)
     *
     * c: Command
     * w: Working dir
     * d: Data package
     * r: Return type (json/xml/plain, default: none, optional)
     * ... Other CLI params (optional)
     */
    public function readCli(): void
    {
        $opt  = getopt('c:w:d:r::', [], $optind);
        $argv = array_slice($_SERVER['argv'], $optind);

        if (!isset($opt['c']) && !empty($argv)) {
            $opt['c'] = array_shift($argv);
        }

        if (!empty($argv)) {
            $this->src_argv = &$argv;
        }

        $this->content_type = 'text/plain';

        if (isset($opt['r'])) {
            if (!in_array($opt['r'], ['json', 'text', 'none', 'xml'], true)) {
                $opt['r'] = 'text';
            }

            foreach ($this->response_types as $type) {
                if (str_contains($type, $opt['r'])) {
                    $this->content_type = &$type;
                    break;
                }
            }

            unset($type);
        }

        if (isset($opt['c'])) {
            $this->src_cmd = trim($this->decodeData($opt['c']));
        }

        if (isset($opt['w'])) {
            $this->cwd_path = trim($this->decodeData($opt['w']));
        }

        if (isset($opt['d'])) {
            $input_data = $this->decodeData($opt['d']);

            if (empty($this->src_input = $this->readInput($input_data))) {
                parse_str($input_data, $this->src_input);
            }

            unset($input_data);
        }

        foreach ($this->cli_handler as $handler) {
            call_user_func($handler, $this);
        }

        unset($opt, $optind, $argv, $handler);
    }

    /**
     * @return void
     */
    public function output(): void
    {
        if (!empty($this->output_handler)) {
            call_user_func(current($this->output_handler), $this);
            return;
        }

        !headers_sent() && header('Content-Type: ' . $this->content_type . '; charset=utf-8');

        $data = 1 === count($this->src_output) ? current($this->src_output) : $this->src_output;

        if (!empty($this->src_msg)) {
            $data = $this->src_msg + ['data' => $data];
        }

        switch ($this->content_type) {
            case 'application/json':
                if (is_array($data) && empty($data)) {
                    $data = (object)$data;
                }

                echo json_encode($data, JSON_FORMAT);
                break;

            case 'application/xml':
                echo $this->toXml((array)$data);
                break;

            case 'text/plain':
                echo is_array($data) ? $this->toString($data) : (string)$data;
                break;

            case 'text/html':
                if (is_string($data) || is_numeric($data)) {
                    echo $data;
                } elseif (isset($data['data']) && is_string($data['data'])) {
                    echo $data['data'];
                } elseif (is_array($data) && is_string($res = current($data))) {
                    echo $res;
                } else {
                    //Force output data as JSON string
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($data, JSON_FORMAT);
                }
                break;

            case 'text/none':
                break;

            default:
                echo '"' . $this->content_type . '" NOT support!';
                break;
        }

        unset($data, $res);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function encodeData(string $value): string
    {
        return $this->base64_marker . base64_encode($value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function decodeData(string $value): string
    {
        if (str_starts_with($value, $this->base64_marker)) {
            $value = substr($value, strlen($this->base64_marker));
            $value = base64_decode($value);
        }

        return $value;
    }

    /**
     * @param array $array
     * @param bool  $root
     *
     * @return string
     */
    public function toXml(array $array, bool $root = true): string
    {
        $xml = $end = '';

        if ($root && 1 < count($array)) {
            $xml .= '<xml>';
            $end = '</xml>';
        }

        foreach ($array as $key => $item) {
            if (is_numeric($key)) {
                $key = 'xml_' . (string)$key;
            }

            $xml .= '<' . $key . '>';
            $xml .= is_array($item) ? self::toXml($item, false) : (!is_numeric($item) ? '<![CDATA[' . $item . ']]>' : $item);
            $xml .= '</' . $key . '>';
        }

        if ($root) {
            $xml .= $end;
        }

        unset($array, $root, $end, $key, $item);
        return $xml;
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public function toString(array $array): string
    {
        $string = '';

        foreach ($array as $key => $value) {
            $string .= (is_string($key) ? $key . ':' . PHP_EOL : '') . "    " . (is_array($value) ? $this->toString($value) : (string)$value . PHP_EOL);
        }

        unset($array, $key, $value);
        return $string;
    }

    /**
     * @return void
     */
    private function readAccept(): void
    {
        if ('' !== $this->content_type) {
            return;
        }

        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $this->content_type = 'application/json';
            return;
        }

        $match_type = 'application/json';
        $match_pos  = strlen($_SERVER['HTTP_ACCEPT']);

        foreach ($this->response_types as $type) {
            if (false === ($pos = stripos($_SERVER['HTTP_ACCEPT'], $type))) {
                continue;
            }

            if ($pos < $match_pos) {
                $match_pos  = $pos;
                $match_type = $type;
            }
        }

        $this->content_type = &$match_type;
        unset($match_type, $match_pos, $type, $pos);
    }

    /**
     * @return string
     */
    private function readUrl(): string
    {
        if (isset($_SERVER['PATH_INFO']) && 1 < strlen($_SERVER['PATH_INFO'])) {
            return substr($_SERVER['PATH_INFO'], 1);
        }

        if (false !== $start = strpos($_SERVER['REQUEST_URI'], '/', 1)) {
            $stop = strpos($_SERVER['REQUEST_URI'], '?');

            if (0 < $len = (false === $stop ? strlen($_SERVER['REQUEST_URI']) : $stop) - ++$start) {
                return substr($_SERVER['REQUEST_URI'], $start, $len);
            }
        }

        unset($start, $stop, $len);
        return '';
    }

    /**
     * @return array
     */
    private function readHttp(): array
    {
        return $_FILES + $_POST + $_GET;
    }

    /**
     * @param string $input
     *
     * @return array
     */
    private function readInput(string $input): array
    {
        if (is_array($data = json_decode($input, true))) {
            unset($input);
            return $data;
        }

        libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = false !== $xml ? json_decode(json_encode($xml), true) : [];
        libxml_clear_errors();

        unset($input, $xml);
        return $data;
    }

    /**
     * @return array
     */
    private function readHeader(): array
    {
        $http_keys   = [];
        $header_data = [];

        foreach ($this->header_keys as $key) {
            $http_keys['HTTP_' . strtoupper(strtr($key, '-', '_'))] = $key;
        }

        $find_keys = array_intersect_key($_SERVER, $http_keys);

        foreach ($find_keys as $key => $value) {
            $header_data[$http_keys[$key]] = $value;
        }

        unset($http_keys, $key, $find_keys, $value);
        return $header_data;
    }

    /**
     * @return array
     */
    private function readCookie(): array
    {
        return array_intersect_key($_COOKIE, array_flip($this->cookie_keys));
    }
}