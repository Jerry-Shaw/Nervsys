<?php

/**
 * Platform Handler
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

namespace core\handler;

use core\handler\platform\lib\os;

class platform implements os
{
    //Platform handler
    private static $handler = '';

    /**
     * Get PHP system path
     */
    public static function sys_path(): string
    {
        return forward_static_call([self::handler(), __FUNCTION__]);
    }

    /**
     * Get system hash
     */
    public static function sys_hash(): string
    {
        return forward_static_call([self::handler(), __FUNCTION__]);
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
        return forward_static_call([self::handler(), __FUNCTION__], $cmd);
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
        return forward_static_call([self::handler(), __FUNCTION__], $cmd);
    }

    /**
     * Get platform handler
     *
     * @return string
     * @throws \Exception
     */
    private static function handler(): string
    {
        if ('' !== self::$handler) {
            return self::$handler;
        }

        self::$handler = '\\' . __CLASS__ . '\\' . strtolower(PHP_OS);

        if (!is_string(realpath(ROOT . trim(strtr(self::$handler, '\\', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . '.php'))) {
            throw new \Exception(PHP_OS . ': NOT support!');
        }

        return self::$handler;
    }
}