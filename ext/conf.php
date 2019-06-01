<?php

/**
 * Config file Extension
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

namespace ext;

class conf
{
    /**
     * Config file directory
     *
     * Related to "ROOT/$dir/"
     * Written in multi-section mode
     * Put as "ROOT/$dir/self::DIR/filename.ini"
     */
    const DIR = 'conf';

    //Configuration pool
    private static $pool = [];

    /**
     * Load config file
     *
     * @param string $dir
     * @param string $name
     */
    public static function load(string $dir, string $name): void
    {
        $file = ROOT . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR . $name . '.ini';

        if (is_array($data = parse_ini_file($file, true, INI_SCANNER_TYPED))) {
            self::$pool = &$data;
        }

        unset($dir, $name, $file, $data);
    }

    /**
     * Get configuration of a section
     *
     * @param string $section
     *
     * @return array
     */
    public static function get(string $section): array
    {
        return self::$pool[$section] ?? [];
    }

    /**
     * Set configuration of a section
     *
     * @param string $section
     * @param array  $config
     */
    public static function set(string $section, array $config): void
    {
        self::$pool[$section] = isset(self::$pool[$section])
            ? array_replace(self::$pool[$section], $config)
            : self::$pool[$section];

        unset($section, $config);
    }
}