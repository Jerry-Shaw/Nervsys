<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
 * Copyright 2016-2021 vicky <904428723@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\App;
use Core\Lib\IOUnit;

/**
 * Class libErrno
 *
 * @package Ext
 */
class libErrno extends Factory
{
    private array $msg_pool   = [];
    private bool  $multi_lang = false;

    /**
     * libErrno constructor.
     *
     * @param bool $multi_lang
     */
    public function __construct(bool $multi_lang = false)
    {
        $this->multi_lang = &$multi_lang;
        unset($multi_lang);
    }

    /**
     * Load error file (root based)
     *
     * @param string $file_name
     * @param string $root_path
     *
     * @return $this
     * @throws \Exception
     */
    public function load(string $file_name, string $root_path = ''): self
    {
        $app = App::new();

        $this->msg_pool = array_replace_recursive($this->msg_pool, $app->parseConf($app->getConfPath($file_name, $root_path), false));

        unset($file_name, $root_path, $app);
        return $this;
    }

    /**
     * Get standard error data
     *
     * @param int    $code
     * @param int    $errno
     * @param string $message
     *
     * @return array
     */
    public function get(int $code, int $errno = 0, string $message = ''): array
    {
        if ('' === $message) {
            $message = $this->msg_pool[$code] ?? 'Error message NOT found!';
        }

        return [
            'code'    => &$code,
            'errno'   => &$errno,
            'message' => $this->multi_lang ? gettext($message) : $message
        ];
    }

    /**
     * Set standard output error
     *
     * @param int    $code
     * @param int    $errno
     * @param string $message
     */
    public function set(int $code, int $errno = 0, string $message = ''): void
    {
        IOUnit::new()->setMsgCode(...array_values($this->get($code, $errno, $message)));
        unset($code, $errno, $message);
    }
}