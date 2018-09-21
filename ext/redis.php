<?php

/**
 * Redis Connector Extension
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

namespace ext;

use core\handler\factory;

class redis extends factory
{
    //Redis arguments
    private $host       = '127.0.0.1';
    private $port       = 6379;
    private $auth       = '';
    private $db         = 0;
    private $prefix     = '';
    private $timeout    = 10;
    private $persist    = true;
    private $persist_id = null;

    //Connection pool
    private static $pool = [];

    /**
     * Config arguments
     *
     * @param array $config
     *
     * @return $this
     */
    public function config(array $config): object
    {
        foreach ($config as $key => $value) {
            if (isset($this->$key)) {
                $this->$key = $value;
            }
        }

        unset($config, $key, $value);
        return $this;
    }

    /**
     * Set host
     *
     * @param string $host
     *
     * @return $this
     */
    public function host(string $host): object
    {
        $this->host = &$host;

        unset($host);
        return $this;
    }

    /**
     * Set port
     *
     * @param int $port
     *
     * @return $this
     */
    public function port(int $port): object
    {
        $this->port = &$port;

        unset($port);
        return $this;
    }

    /**
     * Set auth
     *
     * @param string $auth
     *
     * @return $this
     */
    public function auth(string $auth): object
    {
        $this->auth = &$auth;

        unset($auth);
        return $this;
    }

    /**
     * Set db name
     *
     * @param int $db
     *
     * @return $this
     */
    public function db(int $db): object
    {
        $this->db = &$db;

        unset($db);
        return $this;
    }

    /**
     * Set prefix
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function prefix(string $prefix): object
    {
        $this->prefix = &$prefix;

        unset($prefix);
        return $this;
    }

    /**
     * Set read timeout
     *
     * @param int $timeout
     *
     * @return $this
     */
    public function timeout(int $timeout): object
    {
        $this->timeout = &$timeout;

        unset($timeout);
        return $this;
    }

    /**
     * Set persist type
     *
     * @param bool $persist
     *
     * @return $this
     */
    public function persist(bool $persist): object
    {
        $this->persist = &$persist;

        unset($persist);
        return $this;
    }

    /**
     * Set persist_id
     *
     * @param string $persist_id
     *
     * @return $this
     */
    public function persist_id(string $persist_id): object
    {
        $this->persist    = true;
        $this->persist_id = &$persist_id;

        unset($persist_id);
        return $this;
    }

    /**
     * Redis connector
     *
     * @return \Redis
     * @throws \RedisException
     */
    public function connect(): \Redis
    {
        //Check connection pool
        if (isset(self::$pool[$key = hash('crc32b', json_encode([$this->host, $this->port, $this->db, $this->persist_id]))])) {
            return self::$pool[$key];
        }

        //Factory use Redis instance
        $redis = parent::obtain('Redis');

        //Connect
        $this->persist
            ? $redis->pconnect($this->host, $this->port, $this->timeout, $this->persist_id)
            : $redis->connect($this->host, $this->port, $this->timeout);

        //Set auth
        if ('' !== $this->auth && !$redis->auth($this->auth)) {
            throw new \RedisException('Authentication Failed!', E_USER_ERROR);
        }

        //Set DB
        if (!$redis->select($this->db)) {
            throw new \RedisException('DB [' . $this->db . '] NOT found!', E_USER_ERROR);
        }

        //Set prefix
        if ('' !== $this->prefix) {
            $redis->setOption(\Redis::OPT_PREFIX, $this->prefix . ':');
        }

        //Set serializer
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        //Save connection
        self::$pool[$key] = &$redis;

        unset($key);
        return $redis;
    }
}