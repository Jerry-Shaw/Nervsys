<?php

/**
 * Redis Connector Extension
 *
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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
    protected int    $db     = 0;
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
     * Connect redis
     *
     * @return $this
     * @throws \ReflectionException
     */
    public function connect(): self
    {
        //Destroy existed redis object from factory
        if ($this->redis instanceof \Redis) {
            $this->destroy($this->redis);
        }

        /** @var \Redis $redis */
        $redis = parent::getObj(\Redis::class);

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

        $this->redis = $redis;

        unset($redis);
        return $this;
    }

    /**
     * @param string $name
     * @param array  $arguments
     * @param int    $retry_times
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function run(string $name, array $arguments, int $retry_times = 0): mixed
    {
        try {
            $data = $this->redis->{$name}(...$arguments);
        } catch (\RedisException $exception) {
            $reconnect = false;
            $error_msg = $exception->getMessage();

            foreach ($this->retry_match as $match) {
                if (false !== stripos($error_msg, $match)) {
                    $reconnect = true;
                    break;
                }
            }

            if (0 <= $this->retry_limit && ++$retry_times > $this->retry_limit) {
                $reconnect = false;
            }

            if (!$reconnect) {
                throw new \RedisException($error_msg, E_USER_ERROR);
            }

            $data = $this->connect()->run($name, $arguments, $retry_times);

            unset($exception, $reconnect, $error_msg, $match);
        }

        unset($name, $arguments, $retry_times);
        return $data;
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

        $data = $this->run($name, $arguments);

        unset($name, $arguments);
        return $data;
    }
}