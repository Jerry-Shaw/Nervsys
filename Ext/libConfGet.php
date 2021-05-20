<?php

/**
 * ConfGet Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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
    public array $conf_pool = [];

    /**
     * Load config file (root based)
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

        $this->conf_pool = array_replace_recursive($this->conf_pool, $app->parseConf($app->getConfPath($file_name, $root_path), true));

        unset($file_name, $root_path, $app);
        return $this;
    }

    /**
     * Use loaded conf data by section name
     *
     * @param string $section
     *
     * @return array
     */
    public function use(string $section): array
    {
        return $this->conf_pool[$section] ?? [];
    }
}