<?php

/**
 * Operating System Module
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

namespace core\ctr;

class os
{
    //Operating System
    public static $os = '';

    //PHP environment
    protected static $env = [];

    //System information
    protected static $sys = [];

    //Platform class name
    private static $platform = '';

    /**
     * Run OS check
     */
    private static function run(): void
    {
        //Detect Operating System
        if ('' === self::$os || '' === self::$platform) {
            self::$os = PHP_OS;
            self::$platform = '\\core\\ctr\\os\\' . strtolower(self::$os);
        }

        try {
            if (empty(self::$env)) call_user_func(self::$platform . '::env_info');
            if (empty(self::$sys)) call_user_func(self::$platform . '::sys_info');
        } catch (\Throwable $exception) {
            if (DEBUG) {
                fwrite(STDOUT, self::$os . ' NOT fully supported yet! ' . $exception->getMessage() . PHP_EOL);
                fclose(STDOUT);
                exit;
            }
        }
    }

    /**
     * Get PHP environment information
     *
     * @return array
     */
    public static function get_env(): array
    {
        self::run();
        return self::$env;
    }

    /**
     * Get system hash code
     *
     * @return string
     */
    public static function get_hash(): string
    {
        self::run();
        return hash('sha256', implode('|', json_encode(self::$sys)));
    }
}