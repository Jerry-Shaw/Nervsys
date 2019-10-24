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


    public function call_program(): array
    {
        //CLI results
        $call_results = [];

        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        /** @var \core\lib\std\os $unit_os */
        $unit_os = factory::build(os::class);

        //Process CLI command
        while (!is_null($command = array_shift($unit_pool->cli_group))) {
            //Skip empty command
            if ('' === $command = trim($command)) {
                continue;
            }

            //Build CLI command
            $cli_cmd = '"' . $command . '"' . (!empty($unit_pool->cli_params['argv']) ? ' ' . implode(' ', $unit_pool->cli_params['argv']) : '');

            //Check ret conf
            if ('' === $unit_pool->ret) {
                $cli_cmd = $unit_os->cmd_bg($cli_cmd);
            }

            //Create process
            $process = proc_open(
                $unit_os->cmd_proc($cli_cmd),
                [
                    ['pipe', 'r'],
                    ['pipe', 'w'],
                    ['file', ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . date('Y-m-d') . '_cli_error' . '.log', 'ab+']
                ],
                $pipes
            );

            if (!is_resource($process)) {
                throw new \Exception($command . ': Access denied or command ERROR!', E_USER_WARNING);
            }

            //Send data via pipe
            !empty($unit_pool->cli_params['pipe']) && fwrite($pipes[0], $unit_pool->cli_params['pipe']);

            //Collect result
            if ('' !== $unit_pool->ret) {
                $ret = '';

                while (!feof($pipes[1])) {
                    $ret .= fread($pipes[1], 8192);
                }

                $call_results += [$command => $ret];
                unset($ret);
            }

            //Close pipes (ignore process)
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }

        unset($call_before, $path, $cmd, $command, $class, $method);
        return $call_results;
    }
}