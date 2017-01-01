<?php

/**
 * Language Controlling Module
 * Version 2.6.0
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 彼岸花开 <330931138@qq.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 彼岸花开
 * Copyright 2016-2017 秋水之冰
 *
 * This file is part of ooBase Core.
 *
 * ooBase Core is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ooBase Core is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ooBase Core. If not, see <http://www.gnu.org/licenses/>.
 */
class ctrl_language
{
    //Language
    public static $lang = 'en-US';

    //Available languages
    const lang = ['en-US', 'zh-CN'];

    //Common language keys
    const keys = [
        'nav_home',
        'nav_project',
        'nav_petition',
        'nav_panel',
        'nav_seek',
        'nav_login',
        'nav_join',
        'footer_credits',
        'footer_contribute',
        'menu_panel',
        'menu_message',
        'menu_petition',
        'menu_watch',
        'menu_project',
        'menu_profile',
        'menu_security',
        'menu_module',
        'menu_access_key'
    ];

    /**
     * Load a language pack file from project
     * @param string $file
     * @param string $project
     */
    public static function load(string $file, string $project = '')
    {
        if (isset($_GET['lang'])) $lang = &$_GET['lang'];
        elseif (isset($_COOKIE['lang'])) $lang = &$_COOKIE['lang'];
        elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
            setcookie('lang', $lang, time() + 2592000, '/');
        } else $lang = 'en-US';
        if (!in_array($lang, self::lang, true)) $lang = 'en-US';
        if ('en-US' !== $lang) self::$lang = &$lang;
        $path = '' === $project ? ROOT . '/_language/' : ROOT . '/' . $project . '/_language/';
        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);
        bindtextdomain($file, $path);
        textdomain($file);
        unset($file, $project, $lang, $path);
    }

    /**
     * Get text by language from an array
     * @param array $keys
     * @return array
     */
    public static function get_text(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) $data[$key] = gettext($key);
        unset($keys, $key);
        return $data;
    }

    /**
     * Get common text
     * @return array
     */
    public static function get_common(): array
    {
        self::load('output_common', 'core');
        return self::get_text(self::keys);
    }
}