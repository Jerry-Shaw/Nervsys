<?php

/**
 * MySQL Executor
 *
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2018 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace tools;

use ext\file;
use core\ctr\router;
use core\ctr\router\cli;
use ext\pdo_mysql as mysql;

class sql extends mysql
{
    //Scan dir
    public static $dir = null;

    /**
     * Initialize
     */
    private static function init(): void
    {
        //Check dir value
        if (is_string(self::$dir) && '' !== self::$dir) self::$dir = [self::$dir];
        if (is_array(self::$dir) && !empty(self::$dir)) return;

        //Get dir from CMD
        if (!empty(cli::$cmd_argv)) {
            self::$dir = cli::$cmd_argv;
            return;
        }

        //Get dir from Router variables
        if (isset(router::$data['dir'])) {
            self::$dir = is_array(router::$data['dir']) ? router::$data['dir'] : [router::$data['dir']];
            return;
        }

        //Default dir
        self::$dir = ['sql'];
    }

    /**
     * Import SQL
     *
     * @return array
     */
    public static function import(): array
    {
        //Initialize
        self::init();

        //Check dir value
        if (!is_array(self::$dir) || empty(self::$dir)) return [];

        //Loop dir
        $result = [];
        foreach (self::$dir as $dir) {
            //Get absolute path
            $path = ROOT . '/' . $dir;
            $path = realpath($path);

            //Check path
            if (false === $path) {
                $result[] = 'Notice: ' . $dir . ' not exist!';
                continue;
            }

            //List SQL files
            $files = file::get_list($path, '*.sql');

            //Loop SQL files
            foreach ($files as $file) {
                //Get SQL file content
                $sql = file_get_contents($file);

                //Exec SQL & gather results
                $result[] = -1 !== parent::exec($sql) ? $dir . '/' . basename($file) . ' import succeed!' : $dir . '/' . basename($file) . ' import failed!';
            }
        }

        return $result;
    }
}