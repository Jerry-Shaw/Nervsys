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

class redis extends factory
{
    /** @var \Redis $instance */
    protected $instance = null;

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
     *
     * @throws \RedisException
     */
    public function __construct(
        string $host = '127.0.0.1',
        int $port = 6379,
        string $auth = '',
        int $db = 1,
        string $prefix = '',
        int $timeout = 10,
        bool $persist = true,
        string $persist_id = ''
    )
    {
        //Redis already connected
        if (is_object($this->instance)) {
            return;
        }

        //Build Redis instance
        $this->instance = \core\lib\stc\factory::build(\Redis::class);

        //Connect
        $connect = $persist
            ? $this->instance->pconnect($host, $port, $timeout, $persist_id)
            : $this->instance->connect($host, $port, $timeout);

        //Connect failed
        if (!$connect) {
            throw new \RedisException('Connect Failed!', E_USER_ERROR);
        }

        //Set auth
        if ('' !== $auth && !$this->instance->auth($auth)) {
            throw new \RedisException('Authentication Failed!', E_USER_ERROR);
        }

        //Set DB
        if (!$this->instance->select($db)) {
            throw new \RedisException('DB [' . $db . '] NOT found!', E_USER_ERROR);
        }

        //Set prefix
        if ('' !== $prefix) {
            $this->instance->setOption(\Redis::OPT_PREFIX, $prefix . ':');
        }

        //Set read timeout & serializer mode
        $this->instance->setOption(\Redis::OPT_READ_TIMEOUT, -1);
        $this->instance->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        unset($host, $port, $auth, $db, $prefix, $timeout, $persist, $persist_id, $connect);
    }

    /**
     * Get \Redis instance
     *
     * @return \Redis
     */
    public function get_redis(): \Redis
    {
        return $this->instance;
    }
}