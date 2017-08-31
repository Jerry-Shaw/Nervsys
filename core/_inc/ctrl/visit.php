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
    public static $online = true;

    //Online tags
    public static $online_tags = [];

    /**
     * Map all the SESSION/KEY data to a static variable
     * Get the online status by checking the online tags
     * Grant permission for Cross-Domain request
     */
    public static function start(): void
    {
        //Get HTTP HOST and HTTP ORIGIN ready for Cross-Domain request detection
        $Server_HOST = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $Origin_HOST = $_SERVER['HTTP_ORIGIN'] ?? $Server_HOST;
        //Grant permission for "OPTION" request
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            //Grant basic Cross-Domain request permission for HTTP "OPTIONS" Request and allow HTTP Header "KEY"
            header('Access-Control-Allow-Origin: ' . $Origin_HOST);
            header('Access-Control-Allow-Methods: OPTIONS');
            header('Access-Control-Allow-Headers: KEY');
            //Exit when basic Cross-Domain request permission was granted
            exit;
        }
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
                //Detect client type
                self::$client = self::chk_client($Server_HOST);
                //Process local request, extract data from SESSION or KEY
                if ('LOCAL' === self::$client) !empty($_SESSION) ? self::map_session() : (isset($_SERVER['HTTP_KEY']) ? self::map_key() : '');
                elseif ('REMOTE' === self::$client && isset($_SERVER['HTTP_KEY'])) {
                    //Process remote request, extract data from KEY
                    self::map_key();
                    //Exit when KEY is empty
                    if (empty(self::$key)) exit;
                    //Hold Cross-domain response
                    self::ctrl_CDS($Server_HOST, $Origin_HOST);
                }
                break;
        }
        self::chk_online();//Get online status
        unset($Server_HOST, $Origin_HOST);
    }

    /**
     * Renew KEY to a new timestamp, or, make it expired by passing a passed timestamp
     * Return encrypted KEY after renewing
     *
     * @param int $ExpireAt
     *
     * @return string
     */
    public static function renew(int $ExpireAt): string
    {
        //Return encrypted key directly
        if (empty(self::$key)) return self::get_key();
        //Run expire time checking
        if ($ExpireAt > $_SERVER['REQUEST_TIME']) {
            self::$key['ExpireAt'] = &$ExpireAt;
            self::ctrl_session('add', ['ExpireAt' => &$ExpireAt]);
        } else {
            self::$key = [];
            self::ctrl_session('empty');
        }
        unset($ExpireAt);
        //Return encrypted key
        return self::get_key();
    }

    /**
     * Add content to KEY
     * Return encrypted KEY after adding
     *
     * @param array $data
     *
     * @return string
     */
    public static function add(array $data): string
    {
        //Return encrypted key directly
        if (empty($data)) return self::get_key();
        //Add data
        self::$key = array_merge(self::$key, $data);
        self::ctrl_session('add', $data);
        unset($data);
        //Return encrypted key
        return self::get_key();
    }

    /**
     * Remove content from KEY
     * Return encrypted KEY after removing
     *
     * @param array $keys
     *
     * @return string
     */
    public static function remove(array $keys = []): string
    {
        if (!empty($keys)) {
            foreach ($keys as $key) unset(self::$key[$key]);
            self::ctrl_session('remove', $keys);
            unset($key);
        } else {
            self::$key = [];
            self::ctrl_session('empty');
        }
        unset($keys);
        //Return encrypted key
        return self::get_key();
    }

    /**
     * Cross-Domain Security controller
     *
     * @param $Server_HOST
     * @param $Origin_HOST
     */
    private static function ctrl_CDS($Server_HOST, $Origin_HOST): void
    {
        if (isset(self::$key['CDS']) && 'on' === self::$key['CDS']) {
            //Provide Cross-Domain support for correct KEY request
            header('Access-Control-Allow-Origin: ' . $Origin_HOST);
            header('Access-Control-Allow-Methods: GET, POST');
            header('Access-Control-Allow-Headers: KEY');
        } else {
            //Remove Cross-Domain support when KEY is incorrect
            header('Access-Control-Allow-Origin: ' . $Server_HOST);
            header('Access-Control-Allow-Methods: GET, POST');
            header('Access-Control-Allow-Headers: Accept');
        }
        unset($Server_HOST, $Origin_HOST);
    }

    /**
     * Control content from SESSION
     *
     * @param string $act
     * @param array  $data
     */
    private static function ctrl_session(string $act, array $data = []): void
    {
        //Detect client type
        if ('LOCAL' !== self::$client) return;
        //Operation
        switch ($act) {
            case 'add':
                $_SESSION = array_merge($_SESSION, $data);
                break;
            case 'remove':
                foreach ($data as $key) unset($_SESSION[$key]);
                break;
            case 'empty':
                $_SESSION = [];
                break;
        }
        unset($act, $data);
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
    private static function map_key(): void
    {
        self::$client = 'REMOTE';
        $data = crypt::validate_key($_SERVER['HTTP_KEY']);
        if ('' !== $data) {
            $key = json_decode($data, true);
            if (isset($key) && (!isset($key['ExpireAt']) || (isset($key['ExpireAt']) && $_SERVER['REQUEST_TIME'] < $key['ExpireAt']))) self::$key = &$key;
            unset($key);
        }
        unset($data);
    }

    /**
     * Map SESSION content to key
     */
    private static function map_session(): void
    {
        self::$client = 'LOCAL';
        if (!empty($_SESSION)) self::$key = &$_SESSION;
    }

    /**
     * Check client type
     *
     * @param string $Server_HOST
     *
     * @return string
     */
    private static function chk_client(string $Server_HOST): string
    {
        //Check "HTTP_ORIGIN"
        if (isset($_SERVER['HTTP_ORIGIN']) && $Server_HOST !== $_SERVER['HTTP_ORIGIN']) return 'REMOTE';
        //Check "HTTP_REFERER"
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = parse_url($_SERVER['HTTP_REFERER']);
            //Failed to get main components from "HTTP_REFERER"
            if (false === $referer || !isset($referer['scheme']) || !isset($referer['host'])) return 'REMOTE';
            //Deeply check the components from "HTTP_REFERER"
            $Referer_HOST = $referer['scheme'] . '://' . $referer['host'];
            if (isset($referer['port']) && 80 !== $referer['port']) $Referer_HOST .= ':' . $referer['port'];
            if ($Server_HOST !== $Referer_HOST) return 'REMOTE';
            unset($referer, $Referer_HOST);
        }
        //Detect COOKIE when remote client is not detected
        unset($Server_HOST);
        return self::chk_session();
    }

    /**
     * Check COOKIE Key and Value with SESSION data
     *
     * @return string
     */
    private static function chk_session(): string
    {
        $session_name = session_name();
        return isset($_COOKIE[$session_name]) && $_COOKIE[$session_name] === session_id() ? 'LOCAL' : 'REMOTE';
    }

    /**
     * Get the online status by checking the online tags in KEY
     */
    private static function chk_online(): void
    {
        $tags = !empty(self::$online_tags) ? self::$online_tags : ONLINE_TAGS;
        foreach ($tags as $tag) {
            if (!isset(self::$key[$tag])) {
                self::$online = false;
                return;
            }
        }
        unset($tags, $tag);
    }
}
