<?php

/**
 * Core bridge Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

use core\lib\stc\factory as fty;
use core\lib\std\io;
use core\lib\std\os;
use core\lib\std\pool;
use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class core
 *
 * @package ext
 */
class core
{
    /**
     * Stop NS system
     */
    public static function stop(): void
    {
        fty::build(io::class)->output(fty::build(pool::class));
        exit(0);
    }

    /**
     * Get client IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        return fty::build(pool::class)->ip;
    }

    /**
     * Generate UUID (string hash based)
     *
     * @param string $string
     *
     * @return string
     */
    public static function get_uuid(string $string = ''): string
    {
        if ('' === $string) {
            //Create random string
            $string = uniqid(microtime() . getmypid() . mt_rand(), true);
        }

        $start  = 0;
        $codes  = [];
        $length = [8, 4, 4, 4, 12];
        $string = hash('md5', $string);

        foreach ($length as $len) {
            $codes[] = substr($string, $start, $len);
            $start   += $len;
        }

        $uuid = implode('-', $codes);

        unset($string, $start, $codes, $length, $len);
        return $uuid;
    }

    /**
     * Get log save path
     *
     * @return string
     */
    public static function get_log_path(): string
    {
        return fty::build(pool::class)->conf['log']['save_path'];
    }

    /**
     * Get PHP executable path
     *
     * @return string
     */
    public static function get_php_path(): string
    {
        return fty::build(os::class)->php_path();
    }

    /**
     * Get parsed cmd list
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function get_cmd_list(): array
    {
        $cmd_list = [];

        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = fty::build(pool::class);

        /** @var \core\lib\std\router $unit_router */
        $unit_router = fty::build(router::class);

        /** @var \core\lib\std\reflect $unit_reflect */
        $unit_reflect = fty::build(reflect::class);

        foreach ($unit_pool->router_stack as $router) {
            //Parse CMD
            $cmd_group = call_user_func($router, $unit_pool->cmd);

            if (empty($cmd_group) || !is_array($cmd_group)) {
                continue;
            }

            //Build CMD list
            while (is_array($methods = array_shift($cmd_group))) {
                //Get full class name
                $class = $unit_router->get_cls(array_shift($methods));

                if (empty($methods) && $unit_pool->conf['sys']['auto_call']) {
                    //Get default properties
                    $properties = $unit_reflect->get_class($class)->getDefaultProperties();

                    //Skip class with tz NOT open
                    if (empty($tz_data = isset($properties['tz']) ? (array)$properties['tz'] : [])) {
                        continue;
                    }

                    //Skip class with no public method
                    if (empty($pub_func = $unit_reflect->get_method_list($class, \ReflectionMethod::IS_PUBLIC))) {
                        continue;
                    }

                    //Get method list
                    $method_list = array_column($pub_func, 'name');

                    //Get trust list
                    $methods = !in_array('*', $tz_data, true)
                        ? array_intersect($tz_data, $method_list)
                        : $method_list;

                    //Remove magic methods
                    foreach ($methods as $key => $func) {
                        if (0 === strpos($func, '__')) {
                            unset($methods[$key]);
                        }
                    }

                    //Skip class with no trust method
                    if (empty($methods)) {
                        continue;
                    }

                    unset($properties, $tz_data, $pub_func, $method_list, $key, $func);
                }

                foreach ($methods as $method) {
                    $cmd_list[] = $class . '-' . $method;
                }
            }
        }

        unset($unit_pool, $unit_router, $unit_reflect, $router, $cmd_group, $methods, $class, $method);
        return $cmd_list;
    }

    /**
     * Register custom router parser
     *
     * @param array $router
     */
    public static function register_router_function(array $router): void
    {
        fty::build(pool::class)->router_stack[] = $router;
        unset($router);
    }

    /**
     * Set custom output handler
     *
     * @param array $handler
     */
    public static function set_output_handler(array $handler): void
    {
        fty::build(pool::class)->output_handler = $handler;
        unset($handler);
    }

    /**
     * is CLI running mode
     *
     * @return bool
     */
    public static function is_CLI(): bool
    {
        return fty::build(pool::class)->is_CLI;
    }

    /**
     * is requested vis TLS
     *
     * @return bool
     */
    public static function is_TLS(): bool
    {
        return fty::build(pool::class)->is_TLS;
    }

    /**
     * Add error content
     *
     * @param string $key
     * @param        $error
     */
    public static function add_error(string $key, $error): void
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = fty::build(pool::class);

        //Replace error content
        $unit_pool->error[$key][] = $error;
        unset($key, $error, $unit_pool);
    }

    /**
     * Set error content
     *
     * @param array $error
     */
    public static function set_error(array $error): void
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = fty::build(pool::class);

        //Replace error content
        $unit_pool->error = array_replace_recursive($unit_pool->error, $error);
        unset($error, $unit_pool);
    }

    /**
     * Set data
     *
     * @param string $key
     * @param        $value
     */
    public static function add_data(string $key, $value): void
    {
        fty::build(pool::class)->data[$key] = $value;
        unset($key, $value);
    }

    /**
     * Get data
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public static function get_data(string $key = '')
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = fty::build(pool::class);

        //Find data
        $data = '' === $key ? $unit_pool->data : ($unit_pool->data[$key] ?? null);

        unset($key, $unit_pool);
        return $data;
    }
}