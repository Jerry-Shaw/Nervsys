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

class redis
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

    /**
     * Set host
     *
     * @param string $value
     *
     * @return object
     */
    public function host(string $value): object
    {
        $this->host = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set port
     *
     * @param int $value
     *
     * @return object
     */
    public function port(int $value): object
    {
        $this->port = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set auth
     *
     * @param string $value
     *
     * @return object
     */
    public function auth(string $value): object
    {
        $this->auth = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set db name
     *
     * @param int $value
     *
     * @return object
     */
    public function db(int $value): object
    {
        $this->db = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set prefix
     *
     * @param string $value
     *
     * @return object
     */
    public function prefix(string $value): object
    {
        $this->prefix = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set read timeout
     *
     * @param int $value
     *
     * @return object
     */
    public function timeout(int $value): object
    {
        $this->timeout = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set persist type
     *
     * @param bool $value
     *
     * @return object
     */
    public function persist(bool $value): object
    {
        $this->persist = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set persist_id
     *
     * @param string $value
     *
     * @return object
     */
    public function persist_id(string $value): object
    {
        $this->persist_id = &$value;

        unset($value);
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
        //Factory use Redis instance
        $redis = factory::use('Redis');

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

        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        return $redis;
    }
}