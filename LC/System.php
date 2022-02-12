<?php

/**
 * System Controller library
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

use Nervsys\Lib\App;
use Nervsys\Lib\CORS;
use Nervsys\Lib\Error;

class System extends Factory
{
    public App   $app;
    public CORS  $CORS;
    public Error $error;

    /**
     * System constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->app   = App::new();
        $this->CORS  = CORS::new();
        $this->error = Error::new();
    }

    /**
     * @param string $autoload_path
     * @param bool   $autoload_prepend
     *
     * @return $this
     */
    public function addAutoloadPath(string $autoload_path, bool $autoload_prepend = false): self
    {
        spl_autoload_register(
            static function (string $class) use ($autoload_path): void
            {
                $file_path = $autoload_path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

                if (is_file($file_path)) {
                    require $file_path;
                }

                unset($class, $autoload_path, $file_path);
            },
            true,
            $autoload_prepend
        );

        unset($autoload_path, $autoload_prepend);
        return $this;
    }

    /**
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function CorsAddRecord(string $allow_origin, string $allow_headers = ''): self
    {
        $this->CORS->addRecord($allow_origin, $allow_headers);

        unset($allow_origin, $allow_headers);
        return $this;
    }

    /**
     * @param string $pathname
     *
     * @return $this
     */
    public function AppSetApiPath(string $pathname): self
    {
        $this->app->setApiPath($pathname);

        unset($pathname);
        return $this;
    }

    /**
     * @param string $timezone
     *
     * @return $this
     */
    public function AppSetTimezone(string $timezone): self
    {
        $this->app->setTimezone($timezone);

        unset($timezone);
        return $this;
    }

    /**
     * @param bool $core_debug_mode
     *
     * @return $this
     */
    public function AppSetCoreDebug(bool $core_debug_mode): self
    {
        $this->app->setCoreDebug($core_debug_mode);

        unset($core_debug_mode);
        return $this;
    }
}