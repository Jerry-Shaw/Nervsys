<?php

/**
 * Router module
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace init;

use core\handler\factory;

class router extends factory
{
    //Router symbols
    const SYMBOL = ['-' => '/', '.' => '-'];

    /**
     * router constructor.
     */
    public function __construct()
    {
        if (false === $start = strpos($_SERVER['REQUEST_URI'], '/', 1)) {
            return;
        }

        $uri = explode('/', substr($_SERVER['REQUEST_URI'], $start + 1));
        $cnt = count($uri);

        for ($i = 0; $i < $cnt; ++$i) {
            if (0 === $i) {
                parent::$cmd = strtr($uri[$i], self::SYMBOL);
            } else {
                parent::$data[$uri[$i]] = $uri[++$i] ?? null;
            }
        }
    }
}