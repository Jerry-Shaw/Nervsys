<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Lib\App;
use Nervsys\Core\Lib\Error;
use Nervsys\Core\Lib\Router;
use Nervsys\Core\Mgr\FiberMgr;
use Nervsys\Core\Mgr\ProcMgr;

class libMPC extends Factory
{
    public ProcMgr $procMgr;

    /**
     * @param string $php_path
     * @param int    $proc_num
     * @param int    $watch_timeout
     *
     * @return $this
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function create(string $php_path, int $proc_num = 10, int $watch_timeout = 10000): self
    {
        $app = App::new();

        $cmd = $php_path . "\n"
            . $app->script_path . "\n"
            . '-c' . "\n" . '/' . __CLASS__ . '/childProc';

        $this->procMgr = ProcMgr::new($cmd, $app->root_path)
            ->setWatchTimeout($watch_timeout)
            ->create($proc_num);

        unset($php_path, $proc_num, $watch_timeout, $app, $cmd);
        return $this;
    }

    /**
     * @param string        $cmd
     * @param array         $data
     * @param callable|null $callable
     *
     * @return $this
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function sendCMD(string $cmd, array $data, callable $callable = null): self
    {
        $data['c'] = &$cmd;

        $this->procMgr->sendArgv(json_encode($data), $callable);

        unset($cmd, $data, $callable);
        return $this;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function commit(): void
    {
        $this->procMgr->commit();
    }

    /**
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function childProc(): void
    {
        $error    = Error::new();
        $router   = Router::new();
        $fiberMgr = FiberMgr::new();

        while (true) {
            $stdin = fgets(STDIN);

            if (false === $stdin) {
                return;
            }

            $stdin = trim($stdin);

            if ('' === $stdin) {
                echo "\n";
                continue;
            }

            $data = json_decode($stdin, true);

            if (!is_array($data)) {
                echo "\n";
                continue;
            }

            try {
                $cgi_cmd = $router->parseCgi($data['c']);

                foreach ($cgi_cmd as $cmd_data) {
                    $fiberMgr->async(
                        $fiberMgr->await([parent::getObj($cmd_data[0], $data), $cmd_data[1]], $data),
                        function (): void
                        {
                            switch (func_num_args()) {
                                case 0:
                                    break;
                                case 1:
                                    echo json_encode(func_get_arg(0), JSON_FORMAT);
                                    break;
                                default:
                                    echo json_encode(func_num_args(), JSON_FORMAT);
                                    break;
                            }

                            echo "\n";
                        }
                    );
                }

                $fiberMgr->commit();
            } catch (\Throwable $throwable) {
                $error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }

            unset($stdin, $data, $cgi_cmd, $cmd_data);
        }
    }
}