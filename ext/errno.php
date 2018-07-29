<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

class errno extends system
{
    //Error message pool
    private static $pool = [];

    //Multi-language support
    private static $lang = true;

    /**
     * Error file directory
     *
     * Related to "ROOT/$dir/"
     * Error file should be put in "ROOT/$dir/self::DIR/filename.ini"
     */
    const DIR = 'error';

    /**
     * Load error file
     *
     * @param string $dir
     * @param string $name
     * @param bool   $lang
     *
     * @throws \ErrorException
     */
    public static function load(string $dir, string $name, bool $lang = true)
    {
        $path = ROOT . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $name . '.ini';
        $data = parse_ini_file($path, false);

        if (false === $data) {
            throw new \ErrorException('Failed to read [' . $path . ']!');
        }

        self::$lang = &$lang;
        self::$pool += $data;

        unset($dir, $name, $lang, $path, $data);
    }

    /**
     * Set standard output error
     *
     * @param int $code
     * @param int $errno
     */
    public static function set(int $code, int $errno = 0): void
    {
        parent::$error = self::get($code, $errno);
        unset($code, $errno);
    }

    /**
     * Get standard error result
     * Language pack should be loaded before getting an error message on multi-language support system
     *
     * @param int $code
     * @param int $errno
     *
     * @return array
     */
    public static function get(int $code, int $errno = 0): array
    {
        return isset(self::$pool[$code])
            ? ['code' => &$code, 'err' => &$errno, 'msg' => self::$lang ? gettext(self::$pool[$code]) : self::$pool[$code]]
            : ['code' => &$code, 'err' => &$errno, 'msg' => 'Error message NOT found!'];
    }
}