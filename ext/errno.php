<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
 * Copyright 2016-2019 vicky <904428723@qq.com>
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

use core\lib\std\pool;

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
        $dir = '/' !== $dir ? trim($dir, '\\/') . DIRECTORY_SEPARATOR : '';

        $file = ROOT . DIRECTORY_SEPARATOR . $dir . self::DIR . DIRECTORY_SEPARATOR . $name . '.ini';

        if (is_array($data = parse_ini_file($file, false, INI_SCANNER_TYPED))) {
            self::$lang = &$lang;
            self::$pool = array_replace(self::$pool, $data);
        }

        unset($dir, $name, $lang, $file, $data);
    }

    /**
     * Set standard output error
     *
     * @param int    $code
     * @param int    $errno
     * @param string $message
     */
    public static function set(int $code, int $errno = 0, string $message = ''): void
    {
        //Get error data
        $error = self::get($code, $errno, $message);
        $keys  = array_keys($error);
        foreach ($keys as $key) {
            factory::obtain(pool::class)->error[$key] = $error[$key];
        }

        unset($code, $errno, $message, $error, $keys, $key);
    }

    /**
     * Get standard error data
     *
     * @param int    $code
     * @param int    $errno
     * @param string $message
     *
     * @return array
     */
    public static function get(int $code, int $errno = 0, string $message = ''): array
    {
        if ('' === $message) {
            $message = self::$pool[$code] ?? 'Error message NOT found!';
        }

        return [
            'code'    => &$code,
            'errno'   => &$errno,
            'message' => self::$lang ? gettext($message) : $message
        ];
    }
}