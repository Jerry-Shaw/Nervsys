<?php

/**
 * Proc Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;

class ProcMgr extends Factory
{
    public array $proc_cmd;

    public string $argv_end_char = "\n";

    public string|null $work_dir = null;

    public array $readAt = [0, 20000];

    protected array $procProp = [];

    /**
     * @param string ...$proc_cmd
     */
    public function __construct(string ...$proc_cmd)
    {
        $this->proc_cmd = &$proc_cmd;
    }

    /**
     * @param string $end_char
     *
     * @return $this
     */
    public function argvEndChar(string $end_char): self
    {
        $this->argv_end_char = &$end_char;

        unset($end_char);
        return $this;
    }

    /**
     * @param string $working_path
     *
     * @return $this
     */
    public function setWorkDir(string $working_path): self
    {
        $this->work_dir = &$working_path;

        unset($working_path);
        return $this;
    }

    public function readAt(int $seconds, int $microseconds = null): self
    {
        $this->readAt = [$seconds, $microseconds];

        unset($seconds, $microseconds);
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function run(): self
    {
        $proc = proc_open(
            $this->proc_cmd,
            [
                ['pipe', 'rb'],
                ['socket', 'wb'],
                ['socket', 'wb']
            ],
            $pipes,
            $this->work_dir
        );

        if (!is_resource($proc)) {
            throw new \Exception('Failed to open "' . implode(' ', $this->proc_cmd) . '"', E_USER_ERROR);
        }

        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->procProp['stdin']  = $pipes[0];
        $this->procProp['stdout'] = $pipes[1];
        $this->procProp['stderr'] = $pipes[2];
        $this->procProp['proc']   = $proc;

        register_shutdown_function([$this, 'close']);

        unset($proc, $pipes);
        return $this;
    }

    /**
     * @return bool
     */
    public function isAlive(): bool
    {
        $proc_status = proc_get_status($this->procProp['proc']);
        $proc_alive  = $proc_status['running'] ?? false;

        unset($proc_status);
        return $proc_alive;
    }

    /**
     * @return string
     */
    public function getCmd(): string
    {
        return implode(' ', $this->proc_cmd);
    }

    /**
     * @return array
     */
    public function getMsg(): array
    {
        $read = [
            'stdout' => $this->procProp['stdout'],
            'stderr' => $this->procProp['stderr']
        ];

        $write = $except = $result = [];

        $changed = stream_select($read, $write, $except, $this->readAt[0], $this->readAt[1]);

        if (0 < $changed) {
            $result = [
                'type' => key($read),
                'data' => trim(fgets(current($read)))
            ];
        }

        unset($read, $write, $except, $changed);
        return $result;
    }

    /**
     * @param string $argv
     *
     * @return $this
     */
    public function sendArgv(string $argv): self
    {
        fwrite($this->procProp['stdin'], $argv . $this->argv_end_char);

        unset($argv);
        return $this;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        fclose($this->procProp['stdin']);
        fclose($this->procProp['stdout']);
        fclose($this->procProp['stderr']);
        proc_close($this->procProp['proc']);
    }
}