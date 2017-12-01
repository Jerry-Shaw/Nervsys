<?php

/**
 * Language Extension
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

namespace ext;

class lang
{
    //Language
    public static $lang = 'en-US';

    //Language file path (inside path should be "$lang/LC_MESSAGES/lang_file.mo")
    public static $path = '/language/';

    /**
     * Load language pack from module
     *
     * @param string $file
     */
    public static function load(string $file): void
    {
        //Language detection
        if (isset($_REQUEST['lang'])) $lang = &$_REQUEST['lang'];
        elseif (isset($_COOKIE['lang'])) $lang = &$_COOKIE['lang'];
        elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
            setcookie('lang', $lang, $_SERVER['REQUEST_TIME'] + 2592000, '/');
        } else $lang = 'en-US';
        if (!in_array($lang, LANGUAGE_LIST, true)) $lang = 'en-US';
        //Change default language
        if ('en-US' !== $lang) self::$lang = &$lang;
        //Load language file
        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);
        bindtextdomain($file, ROOT . self::$path);
        textdomain($file);
        unset($file, $lang);
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