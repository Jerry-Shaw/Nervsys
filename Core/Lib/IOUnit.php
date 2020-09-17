<?php

/**
 * NS I/O Unit library
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Core\Lib;

use Core\Factory;

/**
 * Class IOUnit
 *
 * @package Core\Lib
 */
class IOUnit extends Factory
{
    public array $cgi_reader;
    public array $cli_reader;
    public array $output_handler;

    public array $header_keys = [];
    public array $cookie_keys = [];

    public string $src_cmd  = '';
    public string $src_argv = '';

    public array  $src_error  = [];
    public array  $src_input  = [];
    public array  $src_output = [];

    public array  $return_type   = [];
    public string $content_type  = '';
    public string $cli_data_type = '';

    protected string $base64_marker  = 'data:text/argv;base64,';
    protected array  $response_types = ['application/json', 'application/xml', 'text/plain', 'text/html'];

    /**
     * IOUnit constructor.
     */
    public function __construct()
    {
        $this->cgi_reader     = [$this, 'cgiReader'];
        $this->cli_reader     = [$this, 'cliReader'];
        $this->output_handler = [$this, 'outputHandler'];
    }

    /**
     * Set custom ContentType
     *
     * @param string $content_type
     *
     * @return $this
     */
    public function setContentType(string $content_type): self
    {
        $this->content_type = &$content_type;

        unset($content_type);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function setHeaderKeys(string ...$keys): self
    {
        $this->header_keys = &$keys;

        unset($keys);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function setCookieKeys(string ...$keys): self
    {
        $this->cookie_keys = &$keys;

        unset($keys);
        return $this;
    }

    /**
     * Set custom CgiHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setCgiReader(object $handler_object, string $handler_method): self
    {
        $this->cgi_reader = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom CliHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setCliReader(object $handler_object, string $handler_method): self
    {
        $this->cli_reader = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom OutputHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setOutputHandler(object $handler_object, string $handler_method): self
    {
        $this->output_handler = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set error code, NO & Msg
     *
     * @param int    $code
     * @param int    $err_no
     * @param string $err_msg
     *
     * @return $this
     */
    public function setErrorData(int $code, int $err_no, string $err_msg): self
    {
        $this->src_error['code']    = &$code;
        $this->src_error['errno']   = &$err_no;
        $this->src_error['message'] = &$err_msg;

        unset($code, $err_no, $err_msg);
        return $this;
    }

    /**
     * Append error info
     *
     * @param array $err_info
     *
     * @return $this
     */
    public function appendErrorInfo(array $err_info): self
    {
        $this->src_error += $err_info;

        unset($err_info);
        return $this;
    }

    /**
     * Read input data (CGI)
     */
    public function cgiReader(): void
    {
        //Read accept type
        $this->readAccept();

        //Read CMD from URL
        $this->src_cmd = $this->readUrl();

        //Read input data
        $this->src_input = $this->readHttp();
        $this->src_input += $this->readInput(file_get_contents('php://input'));

        //Merge header data
        if (!empty($this->header_keys)) {
            $this->src_input += $this->readHeader();
        }

        //Merge cookie data
        if (!empty($this->cookie_keys)) {
            $this->src_input += $this->readCookie();
        }

        //Fix getting CMD from data
        if ('' === $this->src_cmd && isset($this->src_input['c'])) {
            $this->src_cmd = $this->src_input['c'];
            unset($this->src_input['c']);
        }
    }

    /**
     * Read arguments (CLI)
     *
     * c: CMD (required)
     * d: Data package (required)
     * t: Return type (json/xml/plain, default: none, optional)
     * ... Other CLI params (optional)
     */
    public function cliReader(): void
    {
        //Read opt data
        $opt = getopt('c:d:t::', [], $optind);

        //Read argv data
        $argv = array_slice($_SERVER['argv'], $optind);

        //Pick CMD from argv
        if (!isset($opt['c']) && !empty($argv)) {
            $opt['c'] = array_shift($argv);
        }

        //Fill CLI argv
        if (!empty($argv)) {
            $this->src_argv = implode(' ', $argv);
        }

        //Set default data type
        $this->cli_data_type = 'none';
        $this->content_type  = 'application/json';

        if (isset($opt['t'])) {
            $this->cli_data_type = in_array($opt['t'], ['json', 'text', 'xml'], true) ? $opt['t'] : $opt['t'] = 'text';

            //Find correct content type
            foreach ($this->response_types as $type) {
                if (false !== strpos($type, $opt['t'])) {
                    $this->content_type = &$type;
                    break;
                }
            }

            unset($type);
        }

        //Decode CMD
        if (isset($opt['c'])) {
            $this->src_cmd = $this->decodeData($opt['c']);
        }

        //Decode input data
        if (isset($opt['d'])) {
            $input_data = $this->decodeData($opt['d']);

            if (empty($this->src_input = $this->readInput($input_data))) {
                parse_str($input_data, $this->src_input);
            }
        }

        unset($opt, $optind, $argv);
    }

    /**
     * Encode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public function encodeData(string $value): string
    {
        return $this->base64_marker . base64_encode($value);
    }

    /**
     * Decode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public function decodeData(string $value): string
    {
        if (0 === strpos($value, $this->base64_marker)) {
            $value = substr($value, strlen($this->base64_marker));
            $value = base64_decode($value);
        }

        return $value;
    }

    /**
     * Array content to XML
     *
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

            $xml .= is_array($item)
                ? self::toXml($item, false)
                : (!is_numeric($item) ? '<![CDATA[' . $item . ']]>' : $item);

            $xml .= '</' . $key . '>';
        }

        if ($root) {
            $xml .= $end;
        }

        unset($array, $root, $end, $key, $item);
        return $xml;
    }

    /**
     * Array content to string
     *
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
     * Output data source
     *
     * @param \Core\Lib\IOUnit $io_unit
     */
    public function outputHandler(IOUnit $io_unit): void
    {
        !headers_sent() && header('Content-Type: ' . $io_unit->content_type . '; charset=utf-8');
        $data = 1 === count($io_unit->src_output) ? current($io_unit->src_output) : $io_unit->src_output;

        if (empty($io_unit->src_output) && in_array('object', $io_unit->return_type, true)) {
            $data = (object)[];
        }

        if (!empty($io_unit->src_error)) {
            $data = $io_unit->src_error + ['data' => $data];
        }

        switch ($io_unit->content_type) {
            case 'application/json':
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
                    echo 'Invalid HTML Page!';
                }
                break;

            default:
                echo '"' . $io_unit->content_type . '" NOT support!';
                break;
        }

        unset($io_unit, $data, $res);
    }

    /**
     * Read accept type
     */
    private function readAccept(): void
    {
        //Accept type already defined
        if ('' !== $this->content_type) {
            return;
        }

        //Set default accept type to "application/json"
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            $this->content_type = 'application/json';
            return;
        }

        //Read accept type from header
        $match_type = 'application/json';
        $match_pos  = strlen($_SERVER['HTTP_ACCEPT']);

        foreach ($this->response_types as $type) {
            if (false === $pos = stripos($_SERVER['HTTP_ACCEPT'], $type)) {
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
     * Read CMD from URL
     *
     * @return string
     */
    private function readUrl(): string
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
    private function readHttp(): array
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
    private function readInput(string $input): array
    {
        //Decode data in JSON
        if (is_array($data = json_decode($input, true))) {
            unset($input);
            return $data;
        }

        //Decode data in XML
        libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = false !== $xml ? (array)$xml : [];
        libxml_clear_errors();

        unset($input, $xml);
        return $data;
    }

    /**
     * Read header data
     *
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

        unset($http_keys, $find_keys, $key, $value);
        return $header_data;
    }

    /**
     * Read cookie data
     *
     * @return array
     */
    private function readCookie(): array
    {
        return array_intersect_key($_COOKIE, array_flip($this->cookie_keys));
    }
}