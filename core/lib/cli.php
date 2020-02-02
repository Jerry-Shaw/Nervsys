<?php

/**
 * NS System CLI script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\lib;

use core\lib\stc\error;
use core\lib\stc\factory;
use core\lib\std\os;
use core\lib\std\pool;

/**
 * Class cli
 *
 * @package core
 */
final class cli
{
    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    //Log save path
    private $log_path = '';

    /**
     * cli constructor.
     */
    public function __construct()
    {
        //Build pool
        $this->unit_pool = factory::build(pool::class);

        //Set log path
        $this->log_path = $this->unit_pool->conf['log']['save_path'];

        //Check log path
        if (!is_dir($this->log_path)) {
            mkdir($this->log_path, 0777, true);
            chmod($this->log_path, 0777);
        }
    }

    /**
     * Call external program
     *
     * @return array
     */
    public function call_program(): array
    {
        //CLI results
        $call_results = [];

        /** @var \core\lib\std\os $unit_os */
        $unit_os = factory::build(os::class);

        //Process CLI command
        while (is_array($cmd_pair = array_shift($this->unit_pool->cli_stack))) {
            try {
                //Extract CMD contents
                [$cmd_key, $cmd_value] = $cmd_pair;

                //Skip empty command
                if ('' === $cmd_value = trim($cmd_value)) {
                    continue;
                }

                //Get CMD argv
                $cmd_argv = $this->unit_pool->cli_param['argv'];

                //Build CLI command
                $cli_cmd = '"' . $cmd_value . '"' . (!empty($cmd_argv) ? ' ' . implode(' ', $cmd_argv) : '');

                //Check ret conf
                if ('' === $this->unit_pool->ret) {
                    $cli_cmd = $unit_os->cmd_bg($cli_cmd);
                }

                //Create process
                $process = proc_open(
                    $unit_os->cmd_proc($cli_cmd),
                    [
                        ['pipe', 'r'],
                        ['pipe', 'w'],
                        ['file', $this->log_path . DIRECTORY_SEPARATOR . date('Y-m-d') . '_cli_error' . '.log', 'ab+']
                    ],
                    $pipes
                );

                //Create process failed
                if (!is_resource($process)) {
                    throw new \Exception($cmd_key . ': Access denied or command ERROR!', E_USER_WARNING);
                }

                //Send data via pipe
                '' !== $this->unit_pool->cli_param['pipe'] && fwrite($pipes[0], $this->unit_pool->cli_param['pipe']);

                //Collect result
                if ('' !== $this->unit_pool->ret) {
                    $ret = '';

                    //Read output via pipe
                    while (!feof($pipes[1])) {
                        $ret .= fread($pipes[1], 8192);
                    }

                    $call_results += [$cmd_key => $ret];
                    unset($ret);
                }

                //Close pipes
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                //Close process
                proc_close($process);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable, false);
                unset($throwable);
            }
        }

        unset($unit_os, $cmd_pair, $cmd_key, $cmd_value, $cmd_argv, $cli_cmd, $process, $pipes, $pipe);
        return $call_results;
    }
}