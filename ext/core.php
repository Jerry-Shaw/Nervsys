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

use core\lib\stc\factory;
use core\lib\std\io;
use core\lib\std\os;
use core\lib\std\pool;
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
        factory::build(io::class)->output(factory::build(pool::class));
        exit(0);
    }

    /**
     * Set autoload to target path
     *
     * @param string $path
     */
    public static function autoload(string $path): void
    {
        $path = ROOT . '/' . $path;

        spl_autoload_register(
            static function (string $class) use ($path): void
            {
                //Try to load class file "ROOT/$path/namespace/class.php"
                if (is_file($class_file = $path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php')) {
                    require $class_file;
                }

                unset($class, $path, $class_file);
            }
        );

        unset($path);
    }

    /**
     * Get client IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        return factory::build(pool::class)->ip;
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
     * Get NS default router
     *
     * @return \core\lib\std\router
     */
    public static function get_def_router(): object
    {
        return factory::build(router::class);
    }

    /**
     * Get log save path
     *
     * @return string
     */
    public static function get_log_path(): string
    {
        return factory::build(pool::class)->conf['log']['save_path'];
    }

    /**
     * Get hardware hash value
     *
     * @return string
     */
    public static function get_hw_hash(): string
    {
        return factory::build(os::class)->get_hw_hash();
    }

    /**
     * Get PHP executable path
     *
     * @return string
     */
    public static function get_php_path(): string
    {
        return factory::build(os::class)->get_php_path();
    }

    /**
     * Get input cmd value
     *
     * @return string
     */
    public static function get_cmd_val(): string
    {
        return factory::build(pool::class)->cmd;
    }

    /**
     * Get parsed cmd list
     *
     * @return array
     */
    public static function get_cmd_list(): array
    {
        $cmd_list = [];

        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        /** @var \core\lib\std\router $unit_router */
        $unit_router = factory::build(router::class);

        //Get parsed cmd group
        $cmd_group = $unit_router->parse($unit_pool->cmd);

        //Rebuild cmd list
        foreach ($cmd_group as $item) {
            $cls = $unit_router->get_cls(array_shift($item));

            foreach ($item as $val) {
                $cmd_list[] = $cls . '/' . $val;
            }
        }

        unset($unit_pool, $unit_router, $cmd_group, $item, $cls, $val);
        return $cmd_list;
    }

    /**
     * Register custom router parser
     *
     * @param array $router
     * @param bool  $prepend
     */
    public static function register_router_function(array $router, bool $prepend = true): void
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        $prepend
            ? array_unshift($unit_pool->router_stack, $router)
            : array_push($unit_pool->router_stack, $router);

        unset($router, $prepend, $unit_pool);
    }

    /**
     * Set custom output handler
     *
     * @param array $handler
     */
    public static function set_output_handler(array $handler): void
    {
        factory::build(pool::class)->output_handler = &$handler;
        unset($handler);
    }

    /**
     * is CLI running mode
     *
     * @return bool
     */
    public static function is_CLI(): bool
    {
        return factory::build(pool::class)->is_CLI;
    }

    /**
     * is requested vis TLS
     *
     * @return bool
     */
    public static function is_TLS(): bool
    {
        return factory::build(pool::class)->is_TLS;
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
        $unit_pool = factory::build(pool::class);

        //Replace error content
        $unit_pool->error[$key][] = &$error;
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
        $unit_pool = factory::build(pool::class);

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
        factory::build(pool::class)->data[$key] = &$value;
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
        $unit_pool = factory::build(pool::class);

        //Find data
        $data = '' === $key ? $unit_pool->data : ($unit_pool->data[$key] ?? null);

        unset($key, $unit_pool);
        return $data;
    }
}