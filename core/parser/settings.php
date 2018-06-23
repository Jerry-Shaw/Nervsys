<?php

/**
 * Settings Parser
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

namespace core\parser;

use core\pool\configure;

class settings extends configure
{
    /**
     * Load config
     *
     * @throws \Exception
     */
    public static function load(): void
    {
        //Check CGI mode
        self::check_cgi();

        //Check HTTPS protocol
        self::check_https();

        //Parse settings
        if (false === $conf = parse_ini_file(self::PATH, true)) {
            throw new \Exception('System setting ERROR!');
        }

        //Set include path
        if (isset($conf['PATH'])) {
            if (!empty($conf['PATH'])) {
                self::set_path($conf['PATH']);
            }

            unset($conf['PATH']);
        }

        //Set configuration
        foreach ($conf as $key => $val) {
            $key = strtolower($key);

            if (isset(self::$$key)) {
                self::$$key = $val;
            }
        }

        unset($conf, $path, $key, $val);
    }

    /**
     * Set include path
     *
     * @param array $paths
     */
    private static function set_path(array $paths): void
    {
        //Format paths
        $paths = array_map(
            static function (string $path): string
            {
                $path = strtr($path, '\\', DIRECTORY_SEPARATOR);
                $path = rtrim($path, DIRECTORY_SEPARATOR);

                //Set relative/absolute paths
                $path = 0 !== strpos($path, '/') && false === strpos($path, ':', 1)
                    ? ROOT . $path . DIRECTORY_SEPARATOR
                    : $path . DIRECTORY_SEPARATOR;

                return $path;
            }, $paths
        );

        //Set include path
        set_include_path(implode(PATH_SEPARATOR, $paths));
        unset($paths, $path);
    }

    /**
     * Check running mode
     */
    private static function check_cgi(): void
    {
        self::$is_cgi = 'cli' !== PHP_SAPI;
    }

    /**
     * Check HTTPS protocol
     */
    private static function check_https(): void
    {
        self::$is_https = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }
}