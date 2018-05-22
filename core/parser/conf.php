<?php

/**
 * Config Setting Parser
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

use core\module\data;

class conf
{
    //Config file path
    const CONF_PATH = ROOT . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'conf.ini';

    /**
     * Load config settings
     */
    public static function load(): void
    {
        $path = realpath(self::CONF_PATH);

        if (false === $path) {
            return;
        }

        $conf = parse_ini_file($path, true);

        if (false === $conf) {
            return;
        }

        foreach ($conf as $key => $val) {
            data::$conf[$key] = $val;
        }

        data::$conf['HTTPS'] = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        unset($path, $conf, $key, $val);
    }

    /**
     * Check Cross-origin resource sharing permission
     */
    public static function chk_cors(): void
    {
        if (
            !isset(data::$conf['CORS'])
            || empty(data::$conf['CORS'])
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (data::$conf['HTTPS'] ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
        ) {
            return;
        }

        if (!isset(data::$conf['CORS'][$_SERVER['HTTP_ORIGIN']])) {
            exit;
        }

        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . data::$conf['CORS'][$_SERVER['HTTP_ORIGIN']]);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            exit;
        }
    }

    /**
     * Call INIT functions
     */
    public static function call_init(): void
    {
        if (!isset(data::$conf['INIT']) || empty(data::$conf['INIT'])) {
            return;
        }

        foreach (data::$conf['INIT'] as $key => $item) {
            $class = self::prep_class($key);
            $method = is_string($item) ? [$item] : $item;

            foreach ($method as $function) {
                forward_static_call([$class, $function]);
            }
        }

        unset($key, $item, $class, $method, $function);
    }

    /**
     * Prepare Root Class
     *
     * @param string $library
     *
     * @return string
     */
    private static function prep_class(string $library): string
    {
        $library = ltrim($library, '\\');
        $library = '\\' . strtr($library, '/', '\\');

        return $library;
    }
}