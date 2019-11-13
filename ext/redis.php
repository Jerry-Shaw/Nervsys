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

/**
 * Class redis
 *
 * @package ext
 */
class redis extends factory
{
    //Connection properties
    protected $props = [];

    //Redis options
    protected $db     = 0;
    protected $auth   = '';
    protected $prefix = '';
    protected $method = '';

    /**
     * redis constructor.
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
        int $port = 6379,
        string $auth = '',
        int $db = 0,
        string $prefix = '',
        int $timeout = 10,
        bool $persist = true,
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

        //Copy needed options
        $this->db     = &$db;
        $this->auth   = &$auth;
        $this->prefix = &$prefix;

        unset($host, $port, $auth, $db, $prefix, $timeout, $persist, $persist_id);
    }

    /**
     * Connect Redis
     *
     * @return \Redis
     * @throws \RedisException
     */
    public function connect(): \Redis
    {
        //Build Redis instance
        $redis = \core\lib\stc\factory::build(\Redis::class);

        //Connect Redis
        if (!$redis->{$this->method}(...$this->props)) {
            throw new \RedisException('Connect Failed!', E_USER_ERROR);
        }

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

        return $redis;
    }
}