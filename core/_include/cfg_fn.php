<?php

/**
 * Basic Functions
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author 彼岸花开 <330931138@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
 * Copyright 2016-2017 彼岸花开
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Load library file
 *
 * @param string $module
 * @param string $library
 *
 * @return string library name when loaded or empty string when load failed
 */
function load_lib(string $module, string $library): string
{
    if (!class_exists($library, false)) {
        $position = strrpos($library, '\\');
        $library_file = ROOT . '/' . $module . '/_include/' . (false === $position ? $library : substr($library, $position)) . '.php';
        if (is_file($library_file)) {
            require $library_file;
            if (!class_exists($library, false)) $library = '';
        } else $library = '';
        unset($position, $library_file);
    }
    unset($module);
    return $library;
}

/**
 * Cross module api loader
 *
 * @param string $module
 * @param string $library
 * @param string $method
 *
 * @return array
 */
function load_api(string $module, string $library, string $method): array
{
    $result = [];
    $class = load_lib($module, $library);
    if ('' !== $class) {
        $method_list = get_class_methods($class);
        $api_list = SECURE_API && isset($class::$api) && is_array($class::$api) ? array_keys($class::$api) : [];
        if (in_array($method, $method_list, true) && (in_array($method, $api_list, true) || 'init' === $method || !SECURE_API)) {
            $reflect = new \ReflectionMethod($class, $method);
            if ($reflect->isPublic() && $reflect->isStatic()) {
                try {
                    $result['data'] = $class::$method();
                } catch (\Throwable | \Exception $exception) {
                    $result['data'] = $exception->getMessage();
                }
            }
            unset($reflect);
        }
        unset($api_list, $method_list);
    }
    unset($module, $library, $method, $class);
    return $result;
}

/**
 * Escape all the passing variables and parameters
 */
function escape_request()
{
    if (isset($_GET) && !empty($_GET)) $_GET = escape_requests($_GET);
    if (isset($_POST) && !empty($_POST)) $_POST = escape_requests($_POST);
    if (isset($_REQUEST) && !empty($_REQUEST)) $_REQUEST = escape_requests($_REQUEST);
}

/**
 * Provides escaping filter for escape_request
 *
 * @param array $requests
 *
 * @return array
 */
function escape_requests(array $requests): array
{
    foreach ($requests as $key => $value) $requests[$key] = !is_array($value) ? urlencode($value) : escape_requests($value);
    unset($key, $value);
    return $requests;
}

/**
 * Generates UUID for strings
 * UUID Format: 8-4-4-4-12
 *
 * @param string $string
 *
 * @return string
 */
function get_uuid(string $string = ''): string
{
    if ('' === $string) $string = uniqid(mt_rand(), true);
    elseif (1 === preg_match('/[A-Z]/', $string)) $string = mb_strtolower($string, 'UTF-8');
    $code = hash('sha1', $string . ':UUID');
    $uuid = substr($code, 0, 8);
    $uuid .= '-';
    $uuid .= substr($code, 10, 4);
    $uuid .= '-';
    $uuid .= substr($code, 16, 4);
    $uuid .= '-';
    $uuid .= substr($code, 22, 4);
    $uuid .= '-';
    $uuid .= substr($code, 28, 12);
    $uuid = strtoupper($uuid);
    unset($string, $code);
    return $uuid;
}

/**
 * Provides the CHAR from an UUID
 *
 * @param string $uuid
 * @param int $len
 *
 * @return string
 */
function get_char(string $uuid, int $len = 1): string
{
    $uuid = substr($uuid, 0, $len);
    $char = strtr($uuid, ['A' => '0', 'B' => '1', 'C' => '2', 'D' => '3', 'E' => '4', 'F' => '5', '-' => '6']);
    unset($uuid, $len);
    return $char;
}

/**
 * Sort data by list order (Key mapped)
 *
 * @param array $data
 * @param array $list
 *
 * @return array
 */
function sort_list(array $data, array $list): array
{
    $result = [];
    if (!empty($list) && !empty($data)) {
        foreach ($list as $item) {
            if (isset($data[$item])) $result[$item] = $data[$item];
            else continue;
        }
        unset($item);
    }
    unset($list, $data);
    return $result;
}

/**
 * Get the IP and Language from the client
 * @return array
 */
function get_client_info(): array
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $XFF = &$_SERVER['HTTP_X_FORWARDED_FOR'];
        $client_pos = strpos($XFF, ', ');
        $client_ip = false !== $client_pos ? substr($XFF, 0, $client_pos) : $XFF;
        unset($XFF, $client_pos);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) $client_ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (isset($_SERVER['REMOTE_ADDR'])) $client_ip = $_SERVER['REMOTE_ADDR'];
    elseif (isset($_SERVER['LOCAL_ADDR'])) $client_ip = $_SERVER['LOCAL_ADDR'];
    elseif (false !== getenv('HTTP_X_FORWARDED_FOR')) {
        $XFF = getenv('HTTP_X_FORWARDED_FOR');
        $client_pos = strpos($XFF, ', ');
        $client_ip = false !== $client_pos ? substr($XFF, 0, $client_pos) : $XFF;
        unset($XFF, $client_pos);
    } elseif (false !== getenv('HTTP_CLIENT_IP')) $client_ip = getenv('HTTP_CLIENT_IP');
    elseif (false !== getenv('REMOTE_ADDR')) $client_ip = getenv('REMOTE_ADDR');
    elseif (false !== getenv('LOCAL_ADDR')) $client_ip = getenv('LOCAL_ADDR');
    else $client_ip = '0.0.0.0';
    $client_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $client_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 5) : '';
    $client_info = ['ip' => &$client_ip, 'lang' => &$client_lang, 'agent' => &$client_agent];
    unset($client_ip, $client_lang);
    return $client_info;
}

/**
 * GET the content of a url / POST some data to a url
 *
 * @param string $url
 * @param array $data
 * @param string $access_key
 *
 * @return string
 */
function curl_request(string $url, array $data = [], string $access_key = ''): string
{
    $url_parts = parse_url($url);
    if (false !== $url_parts && isset($url_parts['scheme']) && isset($url_parts['host'])) {
        //Request Method (Depends on requested data)
        $method = !empty($data) ? 'POST' : 'GET';
        //Request User-Agent
        $user_agent = 'Mozilla/5.0 (Compatible; NervSys Data API 1.0.0; Permission Granted by NervSys Data Center)';
        //Format HTTP REQUEST Header
        $request = [];
        $request[] = $method . ' ' . $url_parts['path'] ?? '/' . !isset($url_parts['query']) ? '' : '?' . $url_parts['query'] . ' HTTP/1.1';
        $request[] = 'Host: ' . $url_parts['host'] . !isset($url_parts['port']) || 80 === $url_parts['port'] ? ('https' === $url_parts['scheme'] ? ':443' : '') : ':' . $url_parts['port'];
        $request[] = 'Accept: text/plain,text/html,text/xml,application/json,*;q=0';
        $request[] = 'Accept-Charset: UTF-8,*;q=0';
        $request[] = 'Accept-Encoding: identity,*;q=0';
        $request[] = 'Accept-Language: en-US,en,zh-CN,zh,*;q=0';
        $request[] = 'Connection: keep-alive';
        $request[] = 'User-Agent: ' . $user_agent;
        if ('' !== $access_key) $request[] = 'Access-Key: ' . $access_key;
        //Initial cURL request
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_ENCODING, 'identity,*;q=0');
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $request);
        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $response = curl_exec($curl);
        curl_close($curl);
        unset($method, $user_agent, $request, $curl);
    } else $response = '';
    unset($url, $data, $access_key, $url_parts);
    return (string)$response;
}