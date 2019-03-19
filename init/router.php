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
        //Check PATH INFO
        if (!isset($_SERVER['PATH_INFO'])) {
            return;
        }

        //Cut off first slash
        $path_info = substr($_SERVER['PATH_INFO'], 1);

        //Get URI units
        $unit = false !== strpos($path_info, '/') ? array_filter(explode('/', $path_info)) : [$path_info];

        //Get CMD
        parent::$cmd = strtr(array_shift($unit), self::SYMBOL);

        //Map param
        $cnt = count($unit);
        for ($i = 0; $i < $cnt; ++$i) {
            parent::$data[$unit[$i]] = $unit[++$i] ?? null;
        }
    }
}