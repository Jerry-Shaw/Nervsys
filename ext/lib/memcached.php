<?php

/**
 * Memcached Connector Extension
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

class memcached extends factory
{
    //arguments
    protected $host       = '127.0.0.1';
    protected $port       = 11211;
    protected $prefix     = '';
    protected $compress   = false;
    protected $timeout    = 10;

    //Connection pool
    private static $pool = [];

    /**
     * Memcached connector
     *
     * @return \Memcached
     * @throws \Exception
     */
    public function connect(): \Memcached
    {
        //Check connection pool
        if (isset(self::$pool[$key = hash('crc32b', json_encode([$this->host, $this->port,$this->prefix,$this->compress]))])) {
            return self::$pool[$key];
        }

        $mem = parent::obtain('Memcached');
        //$mem = new \Memcached();
        $mem->addServer($this->host, $this->port);
        $mem->setOption($mem::OPT_COMPRESSION, $this->compress);
        $mem->setOption($mem::OPT_CONNECT_TIMEOUT, $this->timeout * 1000);
        if ($mem->getStats() === false) {
            throw new \Exception('Memcached: Host or Port ERROR!');
        }
        self::$pool[$key] = &$mem;
        return $mem;
    }

    /**
     * Get cache
     *
     * @param $key
     *
     * @return
     * @throws \Exception
     */
    public function get($key)
    {
        $key   = $this->prefix . $key;
        $mem = $this->connect();
        $ret = $mem->get($key);
        if ($mem->getResultCode() === $mem::RES_NOTFOUND) {
            $ret = false;
        }
        return $ret;
    }

    /**
     * Set cache
     *
     * @param $key
     * @param $value
     *
     * @return bool
     * @throws \Exception
     */
    public function set($key, $value): bool
    {
        $key   = $this->prefix . $key;
        $mem = $this->connect();
        return $mem->set($key, $value);
    }


}
