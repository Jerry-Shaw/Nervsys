<?php

/**
 * Platform Handler
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\handler;

use core\handler\platform\lib\os;

class platform implements os
{
    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public static function hw_hash(): string
    {
        return self::OS()::{__FUNCTION__}();
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public static function php_path(): string
    {
        return self::OS()::{__FUNCTION__}();
    }

    /**
     * Build background command
     *
     * @param string $cmd
     *
     * @return string
     * @throws \Exception
     */
    public static function cmd_bg(string $cmd): string
    {
        return self::OS()::{__FUNCTION__}($cmd);
    }

    /**
     * Build proc_open command
     *
     * @param string $cmd
     *
     * @return string
     * @throws \Exception
     */
    public static function cmd_proc(string $cmd): string
    {
        return self::OS()::{__FUNCTION__}($cmd);
    }

    /**
     * Get OS class
     *
     * @return string
     * @throws \Exception
     */
    private static function OS(): string
    {
        static $OS = '';

        //Check and load OS class
        if ('' === $OS && !class_exists($OS = __CLASS__ . '\\' . strtolower(PHP_OS))) {
            throw new \Exception(PHP_OS . ': NOT support!', E_USER_ERROR);
        }

        return $OS;
    }
}