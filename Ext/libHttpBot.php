<?php

/**
 * Robot Extension (Web Crawler)
 *
 * Copyright 2025 秋水之冰 <27206617@qq.com>
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

class libHttpBot extends libHttp
{
    public array $handlers = [
        'read'   => null,
        'write'  => null,
        'delete' => null
    ];

    public bool $follow_robots = true;

    public int $proc_num = 4;

    public string $php_path = '';

    public string $user_agent        = 'Mozilla/5.0 (KHTML, like Gecko; compatible; nervsys/' . NS_VER . '/' . NS_NAME . '; Bot/1.0)';
    public string $html_accept_type  = 'text/html,text/plain,application/xhtml+xml,application/xml,*/*;q=0.8';
    public string $image_accept_type = 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8';

    /**
     * @param string $php_path
     * @param int    $proc_num
     *
     * @return $this
     */
    public function setEnv(string $php_path, int $proc_num = 4): self
    {
        $this->php_path = &$php_path;
        $this->proc_num = &$proc_num;

        unset($php_path, $proc_num);
        return $this;
    }

    /**
     * @param callable $readHandler
     * @param callable $writeHandler
     * @param callable $deleteHandler
     *
     * @return $this
     */
    public function setHandlers(callable $readHandler, callable $writeHandler, callable $deleteHandler): self
    {
        $this->handlers = [$readHandler, $writeHandler, $deleteHandler];

        unset($readHandler, $writeHandler, $deleteHandler);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onRead(callable $callback_func): self
    {
        $this->handlers['read'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onWrite(callable $callback_func): self
    {
        $this->handlers['write'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onDelete(callable $callback_func): self
    {
        $this->handlers['delete'] = &$callback_func;

        unset($callback_func);
        return $this;
    }
}