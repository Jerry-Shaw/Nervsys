<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

use core\system;

class errno
{
    /**
     * Error file directory
     *
     * Related to "ROOT/$dir/"
     * Put as "ROOT/$dir/self::DIR/filename.ini"
     */
    const DIR = 'error';

    //Error message pool
    private static $pool = [];

    //Multi-language support
    private static $lang = true;

    /**
     * Load error file
     *
     * @param string $dir
     * @param string $name
     * @param bool   $lang
     */
    public static function load(string $dir, string $name, bool $lang = true): void
    {
        $file = ROOT . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $name . '.ini';

        if (is_array($data = parse_ini_file($file, false, INI_SCANNER_TYPED))) {
            self::$lang = &$lang;
            self::$pool = &$data;
        }

        unset($dir, $name, $lang, $file, $data);
    }

    /**
     * Set standard output error
     *
     * @param int $code
     * @param int $errno
     */
    public static function set(int $code, int $errno = 0): void
    {
        //Get error data
        $error = self::get($code, $errno);
        $keys  = array_keys($error);

        //Overwrite system error pool
        foreach ($keys as $key) {
            system::$error[$key] = $error[$key];
        }

        unset($code, $errno, $error, $keys, $key);
    }

    /**
     * Get standard error data
     *
     * @param int $code
     * @param int $errno
     *
     * @return array
     */
    public static function get(int $code, int $errno = 0): array
    {
        return [
            'code'    => &$code,
            'errno'   => &$errno,
            'message' => isset(self::$pool[$code])
                ? (self::$lang ? gettext(self::$pool[$code]) : self::$pool[$code])
                : 'Error message NOT found!'
        ];
    }
}