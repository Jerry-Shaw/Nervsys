<?php

/**
 * Log Extension
 *
 * Copyright 2016-2019 leo <2579186091@qq.com>
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

use core\lib\std\pool;

/**
 * Class log
 *
 * @package ext
 */
class log extends factory
{
    //Log pool
    private $log_pool = [];

    //Log file
    private $log_file = 'default.log';

    //Log save path
    private $log_path = ROOT . DIRECTORY_SEPARATOR . '/logs';

    /**
     * log constructor.
     *
     * @param string $log_name
     */
    public function __construct(string $log_name = 'default')
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = \core\lib\stc\factory::build(pool::class);

        //Set log path
        $this->log_path = $unit_pool->conf['log']['save_path'] . DIRECTORY_SEPARATOR . date('Y-m');

        //Check log path
        if (!is_dir($this->log_path)) {
            mkdir($this->log_path, 0777, true);
            chmod($this->log_path, 0777);
        }

        //Set log file
        $this->log_file = date('Ymd') . '-' . $log_name . '.log';
        unset($log_name, $unit_pool);
    }

    /**
     * Add logs
     *
     * @param $logs
     *
     * @return $this
     */
    public function add($logs): object
    {
        $this->log_pool[] = is_array($logs) || is_object($logs) ? json_encode($logs, JSON_PRETTY) : (string)$logs;

        unset($logs);
        return $this;
    }

    /**
     * Save logs
     */
    public function save(): void
    {
        static $file = [];

        if (!isset($file[$key = hash('md5', $log = $this->log_path . DIRECTORY_SEPARATOR . $this->log_file)])) {
            $file[$key] = fopen($log, 'ab+');
            chmod($log, 0666);
        }

        fwrite($file[$key], implode(PHP_EOL, $this->log_pool) . PHP_EOL . PHP_EOL);
        $this->log_pool = [];
        unset($key, $log);
    }
}