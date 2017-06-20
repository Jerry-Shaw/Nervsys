<?php

/**
 * Visit Authority Module
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

namespace core\ctrl;

class visit
{
    //Data pool
    public static $key = [];

    //Client type
    public static $client = '';

    //Online Status
    public static $online = false;

    /**
     * Map all the SESSION/KEY data to a static variable
     * Get the online status by checking the online tags
     * Grant permission for Cross-Domain request
     */
    public static function start()
    {
        //Get HTTP HOST and HTTP ORIGIN ready for Cross-Domain permission detection
        $Server_HOST = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $Origin_HOST = $_SERVER['HTTP_ORIGIN'] ?? $Server_HOST;
        //Process HTTP requests
        if ('OPTIONS' !== $_SERVER['REQUEST_METHOD']) {
            //Start SESSION
            Redis_SESSION ? session::start() : session_start();
            //Detect requests
            switch (self::$client) {
                //Local request
                case 'LOCAL':
                    if (!empty($_SESSION)) self::map_session();
                    break;
                //Remote request
                case 'REMOTE':
                    if (isset($_SERVER['HTTP_KEY'])) self::map_key();
                    break;
                //Auto detect
                default:
                    //Detect REMOTE client
                    self::chk_remote($Server_HOST);
                    //Detect COOKIE when remote client is not detected
                    if ('REMOTE' !== self::$client) self::$client = self::chk_session();
                    //Process KEY
                    if ('LOCAL' === self::$client) {
                        //Extract data from SESSION or KEY
                        if (!empty($_SESSION)) self::map_session();
                        else if (isset($_SERVER['HTTP_KEY'])) self::map_key();
                    } else if ('REMOTE' === self::$client && isset($_SERVER['HTTP_KEY'])) {
                        //Extract data from KEY
                        self::map_key();
                        //Check KEY content
                        if (!empty(self::$key)) {
                            //Check Cross-Domain request permission for Javascript
                            if (isset(self::$key['JS-DEV']) && 'on' === self::$key['JS-DEV']) {
                                //Provide Cross-Domain permission support for correct KEY request
                                header('Access-Control-Allow-Origin: ' . $Origin_HOST);
                                header('Access-Control-Allow-Methods: GET, POST');
                                header('Access-Control-Allow-Headers: KEY');
                            } else {
                                //Remove Cross-Domain permission support when the KEY is incorrect
                                header('Access-Control-Allow-Origin: ' . $Server_HOST);
                                header('Access-Control-Allow-Methods: GET, POST');
                                header('Access-Control-Allow-Headers: Accept');
                            }
                        } else exit;//Exit running if KEY content is empty
                    }
                    break;
            }
            self::$online = self::chk_online();//Get online status
        } else {
            //Grant basic Cross-Domain request permission for HTTP OPTIONS Request and allow HTTP Header "KEY"
            header('Access-Control-Allow-Origin: ' . $Origin_HOST);
            header('Access-Control-Allow-Methods: OPTIONS');
            header('Access-Control-Allow-Headers: KEY');
            exit;//Exit running after basic Cross-Domain request permission was granted
        }
        unset($Server_HOST, $Origin_HOST);
    }

    /**
     * Renew KEY to a new timestamp, or, make it expired by passing a passed timestamp
     * Return new KEY content after renewing
     *
     * @param int $ExpireAt
     *
     * @return string
     */
    public static function renew(int $ExpireAt): string
    {
        if (!empty(self::$key)) {
            if ($ExpireAt > time()) {
                self::$key['ExpireAt'] = &$ExpireAt;
                if ('LOCAL' === self::$client) $_SESSION['ExpireAt'] = &$ExpireAt;
            } else {
                self::$key = [];
                if ('LOCAL' === self::$client) {
                    $_SESSION = [];
                    session_destroy();
                }
            }
        }
        unset($ExpireAt);
        return self::get_key();
    }

    /**
     * Add a key => value pair to KEY
     * Return new KEY content after adding
     *
     * @param string $key
     * @param string $value
     * @param bool $is_int
     *
     * @return string
     */
    public static function add(string $key, string $value, bool $is_int = false): string
    {
        if ('' !== $key) {
            if ($is_int) $value = (int)$value;
            self::$key[$key] = &$value;
            if ('LOCAL' === self::$client) $_SESSION[$key] = &$value;
        }
        unset($key, $value, $is_int);
        return self::get_key();
    }

    /**
     * Remove content from KEY
     * Return new KEY content after removing
     *
     * @param string $key
     *
     * @return string
     */
    public static function remove(string $key = ''): string
    {
        if ('' !== $key) {
            unset(self::$key[$key]);
            if ('LOCAL' === self::$client) unset($_SESSION[$key]);
        } else {
            self::$key = [];
            if ('LOCAL' === self::$client) {
                $_SESSION = [];
                session_destroy();
            }
        }
        unset($key);
        return self::get_key();
    }

    /**
     * Get KEY encrypted content
     *
     * @return string
     */
    private static function get_key(): string
    {
        return !empty(self::$key) ? crypt::create_key(json_encode(self::$key)) : '';
    }

    /**
     * Map KEY content to key
     */
    private static function map_key()
    {
        self::$client = 'REMOTE';
        $data = crypt::validate_key($_SERVER['HTTP_KEY']);
        if ('' !== $data) {
            $key = json_decode($data, true);
            if (isset($key) && (!isset($key['ExpireAt']) || (isset($key['ExpireAt']) && time() < $key['ExpireAt']))) self::$key = &$key;
            unset($key);
        }
        unset($data);
    }

    /**
     * Check remote client
     *
     * @param string $Server_HOST
     */
    private static function chk_remote(string $Server_HOST)
    {
        if (isset($_SERVER['HTTP_ORIGIN']) && $Server_HOST !== $_SERVER['HTTP_ORIGIN']) self::$client = 'REMOTE';
        else if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            if (false === $referer || !isset($referer['scheme']) || !isset($referer['host'])) self::$client = 'REMOTE';
            else {
                $Referer_HOST = $referer['scheme'] . '://' . $referer['host'];
                if (isset($referer['port']) && 80 !== $referer['port']) $Referer_HOST .= ':' . $referer['port'];
                if ($Server_HOST !== $Referer_HOST) self::$client = 'REMOTE';
                unset($Referer_HOST);
            }
            unset($referer);
        }
        unset($Server_HOST);
    }

    /**
     * Check COOKIE Key and Value with SESSION data
     *
     * @return string
     */
    private static function chk_session(): string
    {
        $session_name = session_name();
        $client = isset($_COOKIE[$session_name]) && session_id() === $_COOKIE[$session_name] ? 'LOCAL' : 'REMOTE';
        unset($session_name);
        return $client;
    }

    /**
     * Map SESSION content to key
     */
    private static function map_session()
    {
        self::$client = 'LOCAL';
        if (!empty($_SESSION)) self::$key = &$_SESSION;
    }

    /**
     * Get the online status by checking the online tags in KEY
     */
    private static function chk_online(): bool
    {
        $online = true;
        foreach (ONLINE_TAGS as $tag) {
            if (!isset(self::$key[$tag])) {
                $online = false;
                break;
            } else continue;
        }
        unset($tag);
        return $online;
    }
}