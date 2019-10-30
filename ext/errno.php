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

/**
 * Class errno
 *
 * @package ext
 */
class errno
{
    //Error message pool
    private static $msg_pool = [];

    //Multi-language support
    private static $multi_lang = false;

    /**
     * Load error file
     *
     * @param string $file_path
     * @param string $file_name
     * @param bool   $multi_lang
     */
    public static function load(string $file_path, string $file_name, bool $multi_lang = false): void
    {
        $file_path = '/' !== $file_path ? trim($file_path, '\\/') . DIRECTORY_SEPARATOR : '';
        $msg_file  = ROOT . DIRECTORY_SEPARATOR . $file_path . $file_name . '.ini';

        //Read ini file
        if (is_array($data = parse_ini_file($msg_file, false, INI_SCANNER_TYPED))) {
            self::$multi_lang = &$multi_lang;
            self::$msg_pool   = array_replace(self::$msg_pool, $data);
        }

        unset($file_path, $file_name, $multi_lang, $msg_file, $data);
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
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = \core\lib\stc\factory::build(pool::class);

        //Update message pool
        $unit_pool->error = array_replace_recursive($unit_pool->error, self::get($code, $errno, $message));
        unset($code, $errno, $message, $unit_pool);
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
            $message = self::$msg_pool[$code] ?? 'Error message NOT found!';
        }

        return [
            'code'    => &$code,
            'errno'   => &$errno,
            'message' => self::$multi_lang ? gettext($message) : $message
        ];
    }
}