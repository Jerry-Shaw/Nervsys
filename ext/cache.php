<?php

/**
 * Cache Extension (on Redis)
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
 * Class cache
 *
 * @package ext
 */
class cache extends factory
{
    //Cache key prefix
    const PREFIX = 'CAS:';

    /** @var \Redis $redis */
    public $redis;

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
        $cache = json_encode($data, JSON_FORMAT);

        $result = 0 < $life ? $this->redis->set($key, $cache, $life) : $this->redis->set($key, $cache);

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
        if (false === $cache = $this->redis->get(self::PREFIX . $key)) {
            return [];
        }

        if (!is_array($data = json_decode($cache, true))) {
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
        return $this->redis->del(self::PREFIX . $key);
    }
}