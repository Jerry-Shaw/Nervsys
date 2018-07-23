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

class redis extends \Redis
{
    //Redis settings
    public static $host       = '127.0.0.1';
    public static $port       = 6379;
    public static $auth       = '';
    public static $db         = 0;
    public static $prefix     = '';
    public static $timeout    = 10;
    public static $persist    = true;
    public static $persist_id = null;

    /**
     * redis constructor.
     *
     * @throws \RedisException
     */
    public function __construct()
    {
        parent::__construct();

        self::$persist
            ? parent::pconnect(self::$host, self::$port, self::$timeout, self::$persist_id)
            : parent::connect(self::$host, self::$port, self::$timeout);

        $this->set_option();
    }

    /**
     * Set connect option
     *
     * @throws \RedisException
     */
    private function set_option(): void
    {
        //Set auth
        if ('' !== self::$auth && !$this->auth(self::$auth)) {
            throw new \RedisException('Redis: Authentication Failed!', E_USER_ERROR);
        }

        //Set DB
        if (!$this->select(self::$db)) {
            throw new \RedisException('Redis: DB [' . self::$db . '] NOT exist!', E_USER_ERROR);
        }

        //Set prefix
        if ('' !== self::$prefix) {
            $this->setOption(parent::OPT_PREFIX, self::$prefix . ':');
        }

        $this->setOption(parent::OPT_SERIALIZER, parent::SERIALIZER_NONE);
    }
}