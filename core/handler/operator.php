<?php

/**
 * Operator Handler
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

namespace core\handler;

use core\pool\config as pool_conf;
use core\pool\order;

class operator
{
    /**
     * Call INIT functions
     */
    public static function run_init(): void
    {
        if (empty(pool_conf::$INIT)) {
            return;
        }

        foreach (pool_conf::$INIT as $key => $item) {
            $class = '\\' . strtr(ltrim($key, '\\'), '/', '\\');
            $method = is_string($item) ? [$item] : $item;

            foreach ($method as $function) {
                forward_static_call([$class, $function]);
            }
        }

        unset($key, $item, $class, $method, $function);
    }


    public static function run_cgi(): void
    {





        
        var_dump(order::$cmd, order::$cmd_cgi, order::$param_cgi);


    }


    public static function run_cli(): void
    {

    }


}