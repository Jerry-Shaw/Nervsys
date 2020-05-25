<?php

/**
 * Custom router
 *
 * Copyright 2020 秋水之冰 <27206617@qq.com>
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

namespace app\lib;

use ext\factory;

/**
 * Class router
 *
 * @package app\lib
 */
class router extends factory
{
    /**
     * Restful style
     *
     * @param string $cmd
     *
     * @return array
     */
    public function restful(string $cmd): array
    {
        //Skip when default command detected
        if (false !== strpos($cmd, '-')) {
            return [];
        }

        //Skip when no "/" exist
        if (false === $pos = strrpos($cmd, '/')) {
            return [];
        }

        //Parse restful style command
        return [substr($cmd, 0, $pos++), substr($cmd, $pos)];
    }
}