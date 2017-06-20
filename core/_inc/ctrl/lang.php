<?php

/**
 * Language Module
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

class lang
{
    //Language
    public static $lang = 'en-US';

    /**
     * Load language pack from module
     *
     * @param string $module
     * @param string $file
     */
    public static function load(string $module = '', string $file)
    {
        if (isset($_REQUEST['lang'])) $lang = &$_REQUEST['lang'];
        else if (isset($_COOKIE['lang'])) $lang = &$_COOKIE['lang'];
        else if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
            setcookie('lang', $lang, time() + 2592000, '/');
        } else $lang = 'en-US';
        if (!in_array($lang, LANGUAGE_LIST, true)) $lang = 'en-US';
        if ('en-US' !== $lang) self::$lang = &$lang;
        $path = '' === $module || '/' === $module ? ROOT . '/_lang/' : ROOT . '/' . $module . '/_lang/';
        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);
        bindtextdomain($file, $path);
        textdomain($file);
        unset($module, $file, $lang, $path);
    }

    /**
     * Get text by language from an array
     *
     * @param array $keys
     *
     * @return array
     */
    public static function get_text(array $keys): array
    {
        $data = [];
        //Go over every language key to get the text
        foreach ($keys as $key) $data[$key] = gettext($key);
        unset($keys, $key);
        return $data;
    }
}