<?php

/**
 * Redis Connector Extension
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

use core\handler\factory;

class redis extends factory
{
    //Redis arguments
    protected $host       = '127.0.0.1';
    protected $port       = 6379;
    protected $auth       = '';
    protected $db         = 0;
    protected $prefix     = '';
    protected $timeout    = 10;
    protected $persist    = true;
    protected $persist_id = null;

    /** @var \Redis $instance */
    protected $instance = null;

    //Instance pool
    private static $pool = [];

    /**
     * Redis connector
     *
     * @return $this
     * @throws \RedisException
     */
    public function connect(): object
    {
        $key = $this->build_key();

        if (!isset(self::$pool[$key])) {
            //Obtain Redis instance from factory
            $redis = parent::obtain(\Redis::class);

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
            unset($redis);
        }

        //Copy instance
        $this->instance = self::$pool[$key];

        unset($key);
        return $this;
    }

    /**
     * Get \Redis instance
     *
     * @return \Redis
     * @throws \RedisException
     */
    public function get_redis(): \Redis
    {
        if (!is_object($this->instance)) {
            $this->connect();
        }

        return $this->instance;
    }

    /**
     * Build connection key
     *
     * @return string
     */
    private function build_key(): string
    {
        return hash('crc32b', json_encode([$this->host, $this->port, $this->db, $this->persist_id]));
    }
}