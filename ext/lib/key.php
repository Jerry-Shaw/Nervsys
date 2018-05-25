<?php

/**
 * Crypt Key Generator Interface
 *
 * Copyright 2018 秋水之冰 <27206617@qq.com>
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

namespace ext\lib;

interface key
{
    /**
     * Create Crypt Key
     *
     * @return string
     */
    public static function create(): string;

    /**
     * Extract Keys from Crypt Key
     *
     * @param string $key
     *
     * @return array
     */
    public static function extract(string $key): array;

    /**
     * Create obscured key from Crypt Key
     *
     * @param string $key
     *
     * @return string
     */
    public static function obscure(string $key): string;

    /**
     * Rebuild Crypt Key from obscured Key
     *
     * @param string $key
     *
     * @return string
     */
    public static function rebuild(string $key): string;
}