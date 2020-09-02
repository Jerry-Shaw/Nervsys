<?php

/**
 * Errno Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
 * Copyright 2016-2020 vicky <904428723@qq.com>
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
    public string $path;
    private array $msg_pool   = [];
    private bool  $multi_lang = false;

    /**
     * libErrno constructor.
     *
     * @param string $pathname
     * @param bool   $multi_lang
     */
    public function __construct(string $pathname, bool $multi_lang = false)
    {
        $this->path       = App::new()->root_path . DIRECTORY_SEPARATOR . $pathname;
        $this->multi_lang = &$multi_lang;
        unset($pathname, $multi_lang);
    }

    /**
     * Load error file
     *
     * @param string $file_name
     */
    public function load(string $file_name): void
    {
        $msg_file = $this->path . DIRECTORY_SEPARATOR . $file_name . '.ini';

        if (is_file($msg_file) && is_array($data = parse_ini_file($msg_file, false, INI_SCANNER_TYPED))) {
            $this->msg_pool = array_replace($this->msg_pool, $data);
        }

        unset($file_name, $msg_file, $data);
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
        $error = $this->get($code, $errno, $message);
        IOUnit::new()->setErrorData($error['code'], $error['errno'], $error['message']);

        unset($code, $errno, $message, $error);
    }
}