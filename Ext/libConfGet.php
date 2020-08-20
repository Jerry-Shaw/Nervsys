<?php

/**
 * ConfGet Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

/**
 * Class libConfGet
 *
 * @package Ext
 */
class libConfGet extends Factory
{
    public string $path;
    public array  $pool = [];

    /**
     * libConfGet constructor.
     *
     * @param string $pathname
     */
    public function __construct(string $pathname)
    {
        $this->path = App::new()->root_path . DIRECTORY_SEPARATOR . $pathname;
        unset($pathname);
    }

    /**
     * Load config file (no extension)
     *
     * @param string $filename
     *
     * @return $this
     * @throws \Exception
     */
    public function load(string $filename): self
    {
        if (!is_file($file_path = $this->path . DIRECTORY_SEPARATOR . $filename . '.conf')) {
            throw new \Exception('"' . $file_path . '" NOT found!');
        }

        $config = file_get_contents($file_path);

        if (is_null($data = json_decode($config, true))) {
            try {
                $data = parse_ini_string($config, true, INI_SCANNER_TYPED);
            } catch (\Throwable $throwable) {
                throw new \Exception('Failed to parse "' . $file_path . '"!');
            }
        }

        if (!is_array($data)) {
            throw new \Exception('Type of "' . $filename . '.conf" NOT support!');
        }

        $this->pool = array_replace_recursive($this->pool, $data);

        unset($filename, $file_path, $config, $data);
        return $this;
    }

    /**
     * Get conf by name
     *
     * @param string $name
     *
     * @return array
     */
    public function __get(string $name): array
    {
        return $this->pool[$name] ?? [];
    }
}