<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

use core\system;

class errno extends system
{
    //Multi-language support
    private $lang = true;

    //Error message pool
    private $pool = [];

    /**
     * Error file directory
     * Related to "ROOT/$dir/"
     * Error file should be located in "ROOT/$dir/$this->dir/filename.ini"
     */
    const DIR = 'error';

    /**
     * Load error file
     *
     * @param string $dir
     * @param string $name
     * @param bool   $lang
     *
     * @throws \ErrorException
     */
    public function __construct(string $dir, string $name, bool $lang = true)
    {
        $data = parse_ini_file(ROOT . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $name . '.ini', false);

        if (false === $data) {
            throw new \ErrorException('Failed to read [' . $name . '.ini]!');
        }

        $this->lang = &$lang;
        $this->pool += $data;
        unset($dir, $name, $data);
    }

    /**
     * Set standard output error
     *
     * @param int $code
     * @param int $errno
     */
    public function set(int $code, int $errno = 0): void
    {
        parent::$error = $this->get($code, $errno);
    }

    /**
     * Get standard error result
     * Language pack should be loaded before getting an error message on multi-language support system
     *
     * @param int $code
     * @param int $errno
     *
     * @return array
     */
    public function get(int $code, int $errno = 0): array
    {
        return isset($this->pool[$code])
            ? ['code' => &$code, 'err' => &$errno, 'msg' => $this->lang ? gettext($this->pool[$code]) : $this->pool[$code]]
            : ['code' => &$code, 'err' => &$errno, 'msg' => 'Error message NOT found!'];
    }
}