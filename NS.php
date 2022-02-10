<?php

/**
 * Nervsys main script
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

namespace Nervsys;

class NS
{
    /**
     * @return static
     */
    public static function new(): self
    {
        set_time_limit(0);
        ignore_user_abort(true);

        define('NS_VER', '8.1.0');
        define('NS_ROOT', __DIR__);

        define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

        spl_autoload_register(
            static function (string $class): void
            {
                $file_path = __DIR__ . strtr(strstr($class, '\\'), '\\', DIRECTORY_SEPARATOR) . '.php';

                if (is_file($file_path)) {
                    require $file_path;
                }

                unset($class, $file_path);
            },
            true,
            true
        );

        return new self();
    }


}