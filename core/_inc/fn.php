<?php

/**
 * Basic Functions
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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
 * Cross module api loader
 *
 * @param string $library
 * @param string $method
 *
 * @return array
 */
function load_api(string $library, string $method): array
{
    $class = '\\' !== substr($library, 0, 1) ? '\\' . $library : $library;
    if (class_exists($class, true)) {
        $api_list = SECURE_API && isset($class::$api) && is_array($class::$api) ? array_keys($class::$api) : [];
        if (method_exists($class, $method) && (in_array($method, $api_list, true) || 'init' === $method || !SECURE_API)) {
            $reflect = new \ReflectionMethod($class, $method);
            if ($reflect->isPublic() && $reflect->isStatic()) {
                try {
                    $result['data'] = $class::$method();
                } catch (\Throwable $exception) {
                    $result['data'] = $exception->getMessage();
                }
            } else $result['data'] = 'Method "' . $method . '" in "' . $library . '" NOT permitted!';
            unset($reflect);
        } else $result['data'] = 'Method "' . $method . '" in "' . $library . '" NOT exist or NOT permitted!';
        unset($api_list);
    } else $result['data'] = 'Class "' . $library . '" NOT exist!';
    unset($library, $method, $class);
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
    else if (1 === preg_match('/[A-Z]/', $string)) $string = mb_strtolower($string, 'UTF-8');
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
 *
 * @return array
 */
function get_client_info(): array
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $XFF = &$_SERVER['HTTP_X_FORWARDED_FOR'];
        $client_pos = strpos($XFF, ', ');
        $client_ip = false !== $client_pos ? substr($XFF, 0, $client_pos) : $XFF;
        unset($XFF, $client_pos);
    } else if (isset($_SERVER['HTTP_CLIENT_IP'])) $client_ip = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['REMOTE_ADDR'])) $client_ip = $_SERVER['REMOTE_ADDR'];
    else if (isset($_SERVER['LOCAL_ADDR'])) $client_ip = $_SERVER['LOCAL_ADDR'];
    else if (false !== getenv('HTTP_X_FORWARDED_FOR')) {
        $XFF = getenv('HTTP_X_FORWARDED_FOR');
        $client_pos = strpos($XFF, ', ');
        $client_ip = false !== $client_pos ? substr($XFF, 0, $client_pos) : $XFF;
        unset($XFF, $client_pos);
    } else if (false !== getenv('HTTP_CLIENT_IP')) $client_ip = getenv('HTTP_CLIENT_IP');
    else if (false !== getenv('REMOTE_ADDR')) $client_ip = getenv('REMOTE_ADDR');
    else if (false !== getenv('LOCAL_ADDR')) $client_ip = getenv('LOCAL_ADDR');
    else $client_ip = '0.0.0.0';
    $client_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $client_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 5) : '';
    return ['ip' => &$client_ip, 'lang' => &$client_lang, 'agent' => &$client_agent];
}