<?php

/**
 * Nervsys main class
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

use Nervsys\LC\System;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    exit('Nervsys 8.1+ needs PHP 8.1.0 or higher!');
}

class NS
{
    public System $system;

    /**
     * NS constructor
     *
     * @throws \Exception
     */
    public function __construct()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        define('NS_VER', '8.1.0');
        define('NS_ROOT', __DIR__);

        define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

        define('IS_CLI', 'cli' === PHP_SAPI);
        define('IS_TLS', !IS_CLI
            && (
                (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'])
            )
        );

        spl_autoload_register(
            static function (string $class): void
            {
                $file_path = __DIR__ . DIRECTORY_SEPARATOR . strtr(strstr($class, '\\'), '\\', DIRECTORY_SEPARATOR) . '.php';

                if (is_file($file_path)) {
                    require $file_path;
                }

                unset($class, $file_path);
            },
            true,
            true
        );

        $script_path = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

        if (!is_file($script_path)) {
            $script_path = getcwd() . DIRECTORY_SEPARATOR . $script_path;

            if (!is_file($script_path)) {
                throw new \Exception('Script path NOT detected!', E_USER_ERROR);
            }
        }

        define('SCRIPT_PATH', $script_path);
        define('ROOT_PATH', dirname($script_path, 2));
        define('LOG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'logs');

        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
            chmod(LOG_PATH, 0777);
        }

        $hostname = gethostname();

        define('HOSTNAME', is_string($hostname) ? $hostname : 'localhost');

        $this->system = System::new();
        $this->system->addAutoloadPath(ROOT_PATH, true);

        unset($script_path, $hostname);
    }


    /**
     * @return void
     */
    public function go(): void
    {
        date_default_timezone_set($this->system->app->timezone);
        $this->system->CORS->checkPermission(IS_CLI, IS_TLS);


    }


}