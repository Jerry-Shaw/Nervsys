<?php

/**
 * Execute module
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\LC;

class Caller extends Factory
{
    public Error $error;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->error = Error::new();
    }

    /**
     * @param array $cmd_list
     * @param array $input_data
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runCgi(array $cmd_list, array $input_data): array
    {
        $result = [];

        try {
            $fn_result = call_user_func_array(
                [
                    !Reflect::getMethod($cmd_list[0], $cmd_list[1])->isStatic()
                        ? self::getObj($cmd_list[0], $input_data)
                        : $cmd_list[0],
                    $cmd_list[1]
                ],
                $input_data
            );

            if (!is_null($fn_result)) {
                $result[$cmd_list[2] ?? strtr($cmd_list[0], '\\', '/') . '/' . $cmd_list[1]] = &$fn_result;
            }

            unset($fn_result);
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false);
            unset($throwable);
        }

        unset($cmd_list, $input_data);
        return $result;
    }


    public function runCli(): array
    {

    }


}