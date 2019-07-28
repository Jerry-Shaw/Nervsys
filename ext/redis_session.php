<?php

/**
 * Redis Session Extension
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

class redis_session extends redis
{
    //SESSION key prefix
    const PREFIX = 'SESS:';

    //SESSION life
    public $life = 600;

    /**
     * Start Redis SESSION
     */
    public function start(): void
    {
        //Check SESSION status
        if (PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        //Set SESSION GC configurations
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_probability', 100);
        ini_set('session.gc_maxlifetime', $this->life);

        //Set SESSION handler
        session_set_save_handler(
            [$this, 'session_open'],
            [$this, 'session_close'],
            [$this, 'session_read'],
            [$this, 'session_write'],
            [$this, 'session_destroy'],
            [$this, 'session_gc']
        );

        //Start SESSION
        register_shutdown_function('session_write_close');
        session_start();
    }

    /**
     * session_open
     *
     * @param string $save_path
     * @param string $session_name
     *
     * @return bool
     */
    public function session_open(string $save_path, string $session_name): bool
    {
        unset($save_path, $session_name);
        return true;
    }

    /**
     * session_close
     *
     * @return bool
     */
    public function session_close(): bool
    {
        return true;
    }

    /**
     * session_read
     *
     * @param string $session_id
     *
     * @return string
     */
    public function session_read(string $session_id): string
    {
        return (string)$this->instance->get(self::PREFIX . $session_id);
    }

    /**
     * session_write
     *
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     */
    public function session_write(string $session_id, string $session_data): bool
    {
        $write = $this->instance->set(self::PREFIX . $session_id, $session_data, $this->life);

        unset($session_id, $session_data);
        return (bool)$write;
    }

    /**
     * session_destroy
     *
     * @param string $session_id
     *
     * @return bool
     */
    public function session_destroy(string $session_id): bool
    {
        $this->instance->del(self::PREFIX . $session_id);

        unset($session_id);
        return true;
    }

    /**
     * session_gc
     *
     * @param int $life
     *
     * @return bool
     */
    public function session_gc(int $life): bool
    {
        unset($life);
        return true;
    }
}