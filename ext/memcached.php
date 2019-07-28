<?php
/**
 * Memcached Connector Extension
 *
 * Copyright 2018-2019 tggtzbh <tggtzbh@sina.com>
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

use core\handler\factory;

class memcached extends factory
{
    //Arguments
    protected $host     = '127.0.0.1';
    protected $port     = 11211;
    protected $prefix   = '';
    protected $compress = false;
    protected $timeout  = 10;

    //Connection pool
    private static $pool = [];

    /**
     * Memcached connector
     *
     * @return \Memcached
     */
    public function connect(): object
    {
        //Check connection pool
        if (isset(self::$pool[$key = hash('crc32b', json_encode([$this->host, $this->port, $this->prefix, $this->compress]))])) {
            return self::$pool[$key];
        }

        $memcached = parent::obtain(\Memcached::class);
        $memcached->addServer($this->host, $this->port);
        $memcached->setOption(\Memcached::OPT_COMPRESSION, $this->compress);
        $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->timeout * 1000);

        if ($memcached->getStats() === false) {
            throw new \MemcachedException('Memcached: Host or Port ERROR!', E_USER_ERROR);
        }

        self::$pool[$key] = &$memcached;

        unset($key);
        return $memcached;
    }

    /**
     * Get cache
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function get(string $key)
    {
        $memcached = $this->connect();
        $cache     = $memcached->get($this->prefix . $key);

        if ($memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            $cache = null;
        }

        return $cache;
    }

    /**
     * Set cache
     *
     * @param string $key
     * @param        $value
     * @param int    $expiration
     *
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 0): bool
    {
        return $this->connect()->set($this->prefix . $key, $value, $expiration);
    }

    /**
     * Append data to an existing string item
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     */
    public function append(string $key, string $value): bool
    {
        return $this->connect()->append($this->prefix . $key, $value);
    }

    /**
     * Prepend data to an existing string item
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     */
    public function prepend(string $key, string $value): bool
    {
        return $this->connect()->prepend($this->prefix . $key, $value);
    }

    /**
     * Delete cache
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->connect()->delete($this->prefix . $key);
    }

    /**
     * decrement
     * Notice:  The element must be numeric
     *          If the operation would decrease the value below 0, the new value will be 0.
     *
     * @param string $key
     * @param int    $offset
     *
     * @return int
     */
    public function decrement(string $key, int $offset = 1): int
    {
        return $this->connect()->decrement($this->prefix . $key, $offset);
    }

    /**
     * increment
     * Notice:  The element must be numeric
     *
     * @param string $key
     * @param int    $offset
     *
     * @return int
     */
    public function increment(string $key, int $offset = 1): int
    {
        return $this->connect()->increment($this->prefix . $key, $offset);
    }
}