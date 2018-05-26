<?php

/**
 * Errno Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ext;

use core\pool\unit;

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
     *
     * @throws \Exception
     */
    public static function load(string $module, string $file): void
    {
        $file = DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . self::$dir . DIRECTORY_SEPARATOR . $file . '.ini';

        $path = realpath(ROOT . $file);

        if (false === $path) {
            throw new \Exception('[' . $file . '] NOT found!');
        }

        $error = parse_ini_file($path, false);

        if (false === $error) {
            throw new \Exception('[' . $file . '] ERROR!');
        }

        self::$pool += $error;

        unset($module, $file, $path, $error);
    }

    /**
     * Set standard error result into unit error
     *
     * @param int $code
     * @param int $errno
     */
    public static function set(int $code, int $errno = 0): void
    {
        unit::$error = self::get($code, $errno);
    }

    /**
     * Get standard error result
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