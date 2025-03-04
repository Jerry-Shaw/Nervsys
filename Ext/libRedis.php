<?php

/**
 * Redis Connector Extension
 *
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

class libRedis extends Factory
{
    public \Redis|null $redis;

    public int   $retry_limit = 10;
    public array $retry_match = ['lost', 'went away'];

    //Redis options
    protected int $db = 0;

    protected string $auth   = '';
    protected string $prefix = '';
    protected string $method = '';

    //Connection properties
    protected array $props = [];

    /**
     * libRedis constructor.
     *
     * @param string $host
     * @param int    $port
     * @param string $auth
     * @param int    $db
     * @param string $prefix
     * @param int    $timeout
     * @param bool   $persist
     * @param string $persist_id
     */
    public function __construct(
        string $host = '127.0.0.1',
        int    $port = 6379,
        string $auth = '',
        int    $db = 0,
        string $prefix = '',
        int    $timeout = 10,
        bool   $persist = true,
        string $persist_id = ''
    )
    {
        //Build connection properties
        if ($persist) {
            $this->method = 'pconnect';
            $this->props  = [$host, $port, $timeout, $persist_id];
        } else {
            $this->method = 'connect';
            $this->props  = [$host, $port, $timeout];
        }

        //Set redis to null
        $this->redis = null;

        //Copy needed options
        $this->db     = &$db;
        $this->auth   = &$auth;
        $this->prefix = &$prefix;

        unset($host, $port, $auth, $db, $prefix, $timeout, $persist, $persist_id);
    }

    /**
     * Auto reconnect with limited times
     *
     * @param int $retry_times
     *
     * @return $this
     */
    public function autoReconnect(int $retry_times): self
    {
        $this->retry_limit = &$retry_times;

        unset($retry_times);
        return $this;
    }

    /**
     * Connection/Reconnection
     *
     * @param int $retry_times
     *
     * @return $this|self
     * @throws \RedisException
     * @throws \ReflectionException
     */
    public function connect(int $retry_times = 0): self
    {
        //Destroy existed redis object from factory
        if ($this->redis instanceof \Redis) {
            $this->destroy($this->redis);
        }

        /** @var \Redis $redis */
        $redis = parent::getObj(\Redis::class);

        try {
            //Connect
            $redis->{$this->method}(...$this->props);

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

            //Set read timeout & serializer mode
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);
            $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
        } catch (\RedisException $exception) {
            $error = $exception->getMessage();

            foreach ($this->retry_match as $match) {
                if (false === stripos($error, $match)) {
                    continue;
                }

                if (-1 === $this->retry_limit || $retry_times < $this->retry_limit) {
                    unset($exception, $error, $match);
                    return $this->connect(++$retry_times);
                }
            }

            throw new \RedisException($error, E_USER_ERROR);
        }

        $this->redis = &$redis;

        unset($retry_times, $redis);
        return $this;
    }

    /**
     * Call Redis method
     *
     * @throws \ReflectionException
     * @throws \RedisException
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (is_null($this->redis)) {
            $this->connect();
        }

        try {
            $data = $this->redis->{$name}(...$arguments);
        } catch (\RedisException) {
            $data = $this->connect()->redis->{$name}(...$arguments);
        }

        unset($name, $arguments);
        return $data;
    }
}