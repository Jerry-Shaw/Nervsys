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
use core\lib\std\os;
use core\lib\std\pool;
use core\ns;

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
        ns::output(true);
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
     * Get log save path
     *
     * @return string
     */
    public static function get_log_path(): string
    {
        return factory::build(pool::class)->conf['log']['save_path'];
    }

    /**
     * Get PHP executable path
     *
     * @return string
     */
    public static function get_php_path(): string
    {
        return factory::build(os::class)->php_path();
    }

    /**
     * Register CMD router parser
     *
     * @param array $router
     */
    public static function register_router(array $router): void
    {
        factory::build(pool::class)->router_stack[] = $router;
        unset($router);
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
        factory::build(pool::class)->data[$key] = $value;
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