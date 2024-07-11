<?php

/**
 * Session Extension (on Redis)
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

class libSessionOnRedis extends Factory
{
    const PREFIX = 'SESS:';

    public \Redis|libRedis $redis;

    protected int $life = 600;

    /**
     * libSession constructor.
     *
     * @param \Redis|libRedis $redis
     */
    public function __construct(\Redis|libRedis $redis)
    {
        //Check SESSION status
        if (PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        $this->redis = &$redis;

        //Set SESSION GC configurations
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_probability', 100);
        ini_set('session.gc_maxlifetime', $this->life);

        //Set SESSION handler
        session_set_save_handler(
            [$this, 'sessionOpen'],
            [$this, 'sessionClose'],
            [$this, 'sessionRead'],
            [$this, 'sessionWrite'],
            [$this, 'sessionDestroy'],
            [$this, 'sessionGC']
        );

        register_shutdown_function('session_write_close');
        session_start();

        unset($redis);
    }

    /**
     * sessionOpen
     *
     * @param string $save_path
     * @param string $session_name
     *
     * @return bool
     */
    public function sessionOpen(string $save_path, string $session_name): bool
    {
        unset($save_path, $session_name);
        return true;
    }

    /**
     * sessionClose
     *
     * @return bool
     */
    public function sessionClose(): bool
    {
        return true;
    }

    /**
     * sessionRead
     *
     * @param string $session_id
     *
     * @return string
     * @throws \RedisException
     */
    public function sessionRead(string $session_id): string
    {
        return (string)$this->redis->get(self::PREFIX . $session_id);
    }

    /**
     * sessionWrite
     *
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     * @throws \RedisException
     */
    public function sessionWrite(string $session_id, string $session_data): bool
    {
        $write = $this->redis->set(self::PREFIX . $session_id, $session_data, $this->life);

        unset($session_id, $session_data);
        return (bool)$write;
    }

    /**
     * sessionDestroy
     *
     * @param string $session_id
     *
     * @return bool
     * @throws \RedisException
     */
    public function sessionDestroy(string $session_id): bool
    {
        $this->redis->del(self::PREFIX . $session_id);

        unset($session_id);
        return true;
    }

    /**
     * sessionGC
     *
     * @param int $life
     *
     * @return bool
     */
    public function sessionGc(int $life): bool
    {
        unset($life);
        return true;
    }
}