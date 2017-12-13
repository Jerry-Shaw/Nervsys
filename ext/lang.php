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
    /**
     * Language file directory
     * Related to "ROOT/module/"
     * Language file should be located in "ROOT/module/$dir/$lang/LC_MESSAGES/filename.mo"
     *
     * @var string
     */
    public static $dir = 'language';

    //Language
    public static $lang = 'en-US';

    /**
     * Load language pack from module
     *
     * @param string $module
     * @param string $file
     */
    public static function load(string $module, string $file): void
    {
        putenv('LANG=' . self::$lang);
        setlocale(LC_ALL, self::$lang);
        bindtextdomain($file, ROOT . '/' . $module . '/' . self::$dir . '/');
        textdomain($file);

        unset($module, $file);
    }

    /**
     * Translate list in language
     *
     * @param array $list
     */
    public static function trans(array &$list): void
    {
        foreach ($list as $key => $item) {
            unset($list[$key]);
            $list[$item] = gettext($item);
        }

        unset($key, $item);
    }
}