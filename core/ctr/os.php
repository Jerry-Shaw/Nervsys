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
    //OS name
    public static $os = '';

    //PHP path
    protected static $env = '';

    //System hash
    protected static $hash = '';

    //Platform name
    private static $platform = '';

    /**
     * Run OS controller
     *
     * @param string $method
     * @param array  $data
     *
     * @return string
     * @throws \Exception
     */
    private static function run(string $method, array $data = []): string
    {
        //Get OS & build namespace
        if ('' === self::$os) self::$os = PHP_OS;
        if ('' === self::$platform) self::$platform = '\\core\\ctr\\os\\' . strtolower(self::$os);

        //Check OS Controller
        if (false === realpath(ROOT . strtr(self::$platform, '\\', '/') . '.php')) throw new \Exception(self::$os . ' Controller NOT found!');

        //Run OS method
        $result = empty($data) ? forward_static_call([self::$platform, $method]) : forward_static_call_array([self::$platform, $method], $data);

        unset($method, $data);
        return $result;
    }

    /**
     * Get PHP path
     *
     * @return string
     * @throws \Exception
     */
    public static function get_env(): string
    {
        return '' !== self::$env ? self::$env : self::$env = self::run('php_env');
    }

    /**
     * Get system hash
     *
     * @return string
     * @throws \Exception
     */
    public static function get_hash(): string
    {
        return '' !== self::$hash ? self::$hash : self::$hash = self::run('sys_hash');
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
        return self::run('cmd_bg', [$cmd]);
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
        return self::run('cmd_proc', [$cmd]);
    }
}