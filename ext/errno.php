<?php

/**
 * Errno Extension
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

class errno
{
    /**
     * Error file directory
     * Related to "ROOT/module/"
     * Error file should be located in "ROOT/module/$dir/filename.ini"
     *
     * @var string
     */
    public static $dir = 'error';

    //Multi-language support
    public static $lang = true;

    //Error message pool
    private static $pool = [];

    /**
     * Load error file from module
     *
     * @param string $module
     * @param string $file
     */
    public static function load(string $module, string $file): void
    {
        $file = '/' . $module . '/' . self::$dir . '/' . $file . '.ini';

        $path = realpath(ROOT . $file);
        if (false === $path) {
            debug(__CLASS__, '[' . $file . '] NOT found!');
            return;
        }

        $error = parse_ini_file($path, false);
        if (false === $error) {
            debug(__CLASS__, '[' . $file . '] Incorrect!');
            return;
        }

        self::$pool += $error;
        unset($module, $file, $path, $error);
    }

    /**
     * Get a standard error result
     * Language pack needs to be loaded before getting an error message on multi-language support system
     *
     * @param int $code
     * @param int $errno
     *
     * @return array
     */
    public static function get(int $code, int $errno = 0): array
    {
        return isset(self::$pool[$code])
            ? ['err' => &$errno, 'code' => &$code, 'msg' => self::$lang ? gettext(self::$pool[$code]) : self::$pool[$code]]
            : ['err' => &$errno, 'code' => &$code, 'msg' => 'Error message NOT found!'];
    }
}