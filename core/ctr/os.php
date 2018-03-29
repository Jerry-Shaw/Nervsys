<?php

/**
 * Operating System Module
 *
 * Copyright 2016-2017 Jerry Shaw <jerry-shaw@live.com>
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