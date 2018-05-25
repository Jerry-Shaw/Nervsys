<?php

/**
 * OS handler interface
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

namespace core\handler\platform\lib;

interface os
{
    /**
     * Get PHP system path
     */
    public static function sys_path(): string;

    /**
     * Get system hash
     */
    public static function sys_hash(): string;

    /**
     * Build background command
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_bg(string $cmd): string;

    /**
     * Build proc_open command
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_proc(string $cmd): string;
}