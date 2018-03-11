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
        if ('' === self::$os) self::$os = PHP_OS;

        //Build Platform Namespace
        if ('' === self::$platform) self::$platform = '\\core\\ctr\\os\\' . strtolower(self::$os);

        //Check OS Controller File
        if (false === realpath(ROOT . strtr(self::$platform, '\\', '/') . '.php')) throw new \Exception(self::$os . ' Controller NOT found!');
    }

    /**
     * Get PHP environment information
     *
     * @return array
     * @throws \Exception
     */
    public static function get_env(): array
    {
        self::run();

        if (empty(self::$env)) forward_static_call([self::$platform, 'info_env']);

        return self::$env;
    }

    /**
     * Get system hash code
     *
     * @return string
     * @throws \Exception
     */
    public static function get_hash(): string
    {
        self::run();

        if (empty(self::$sys)) forward_static_call([self::$platform, 'info_sys']);

        return hash('sha256', json_encode(self::$sys));
    }

    /**
     * Build command for background process
     *
     * @param string $cmd
     *
     * @return string
     * @throws \Exception
     */
    public static function cmd_bg(string $cmd): string
    {
        self::run();

        return forward_static_call([self::$platform, 'cmd_bg'], $cmd);
    }

    /**
     * Build command for proc_open
     *
     * @param string $cmd
     *
     * @return string
     * @throws \Exception
     */
    public static function cmd_proc(string $cmd): string
    {
        self::run();

        return forward_static_call([self::$platform, 'cmd_proc'], $cmd);
    }
}