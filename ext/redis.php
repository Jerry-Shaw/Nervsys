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
    public $host       = '127.0.0.1';
    public $port       = 6379;
    public $auth       = '';
    public $db         = 0;
    public $prefix     = '';
    public $timeout    = 10;
    public $persist    = true;
    public $persist_id = null;

    /**
     * Set arguments
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return object
     * @throws \RedisException
     */
    public function __call(string $name, array $arguments): object
    {
        if (!isset($this->$name)) {
            throw new \RedisException('Unsupported property: ' . $name, E_USER_ERROR);
        }

        $this->$name = reset($arguments);

        unset($name, $arguments);
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