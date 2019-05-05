<?php

/**
 * Redis Cache Extension
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

class redis_cache extends redis
{
    //Cache key prefix
    const PREFIX = 'CAS:';

    /** @var \Redis $connect */
    private $connect = null;

    /**
     * Connect to Redis
     *
     * @return $this
     * @throws \RedisException
     */
    public function connect(): object
    {
        $this->connect = parent::connect();
        return $this;
    }

    /**
     * Set cache
     *
     * @param string $key
     * @param array  $data
     * @param int    $life
     *
     * @return bool
     */
    public function set(string $key, array $data, int $life = 600): bool
    {
        $key   = self::PREFIX . $key;
        $cache = json_encode($data);

        $result = 0 < $life ? $this->connect->set($key, $cache, $life) : $this->connect->set($key, $cache);

        unset($key, $data, $life, $cache);
        return $result;
    }

    /**
     * Get cache
     *
     * @param string $key
     *
     * @return array
     */
    public function get(string $key): array
    {
        $cache = $this->connect->get(self::PREFIX . $key);

        if (false === $cache) {
            return [];
        }

        $data = json_decode($cache, true);

        if (!is_array($data)) {
            return [];
        }

        unset($key, $cache);
        return $data;
    }

    /**
     * Delete cache
     *
     * @param string $key
     *
     * @return int
     */
    public function del(string $key): int
    {
        $result = $this->connect->del(self::PREFIX . $key);

        unset($key);
        return $result;
    }
}