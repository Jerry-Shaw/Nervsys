<?php
namespace ext;

use core\lib\stc\factory;
use core\lib\std\os;
use core\lib\std\pool;

class request
{
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
     * Get client IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        return factory::build(pool::class)->ip;
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

    /**
     * Get PHP executable path
     *
     * @return string
     */
    public static function get_php_path(): string
    {
        return factory::build(os::class)->php_path();
    }

}