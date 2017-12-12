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
    //Language file directory (Related to ROOT/module/, language file should be located in "ROOT/module/$dir/$lang/LC_MESSAGES/lang_file.mo")
    public static $dir = 'language';

    //Language
    public static $lang = 'en-US';

    /**
     * Load language pack from module
     *
     * @param string $file
     */
    public static function load(string $file): void
    {
        putenv('LANG=' . self::$lang);
        setlocale(LC_ALL, self::$lang);
        bindtextdomain($file, ROOT . '/' . self::$dir . '/');
        textdomain($file);
        unset($file);
    }

    /**
     * Get text from an array
     *
     * @param array $keys
     *
     * @return array
     */
    public static function get_text(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) $data[$key] = gettext($key);
        unset($keys, $key);
        return $data;
    }
}