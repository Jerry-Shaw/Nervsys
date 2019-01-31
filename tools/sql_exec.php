<?php

/**
 * SQL executor
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

namespace tools;

use core\handler\factory;

use ext\file;
use ext\pdo_mysql;

class sql_exec extends factory
{
    /**
     * @var array
     */
    public static $tz = [
        'import' => []
    ];

    /**
     * Import SQL
     *
     * @throws \Exception
     */
    public static function import(): void
    {
        //Check CLI
        if (!parent::$is_CLI) {
            throw new \Exception('Please run under CLI mode!', E_USER_ERROR);
        }

        //Get dir from CLI argv
        if (empty($dirs = parent::$param_cli['argv'])) {
            echo 'Directory NOT set!';
            return;
        }

        //Get MySQL connection
        $mysql = pdo_mysql::use('mysql');

        //Process
        foreach ($dirs as $dir) {
            //Get absolute path
            if (false === $path = realpath(ROOT . $dir)) {
                echo '"' . $dir . '" NOT exist!';
                continue;
            }

            //Get SQL files
            $length = strlen(ROOT) - 1;
            $files  = file::get_list($path, '*.sql');

            //Import SQL files
            foreach ($files as $file) {
                $name = substr($file, $length);

                echo 0 <= $mysql->exec(file_get_contents($file))
                    ? '"' . $name . '" import succeed!'
                    : '"' . $name . '" import failed!';
            }
        }

        unset($dirs, $mysql, $dir, $path, $length, $files, $file, $name);
    }
}