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

/**
 * Class conf
 *
 * @package ext
 */
class conf
{
    //Configuration pool
    private static $pool = [];

    /**
     * Load config file
     *
     * @param string $file_path
     * @param string $file_name
     *
     * @return array
     */
    public static function load(string $file_path, string $file_name): array
    {
        $file_path = '/' !== $file_path ? trim($file_path, "/\\") . DIRECTORY_SEPARATOR : '';
        $conf_file = ROOT . DIRECTORY_SEPARATOR . $file_path . $file_name . '.ini';

        is_array($data = parse_ini_file($conf_file, true, INI_SCANNER_TYPED))
            ? self::$pool = array_replace(self::$pool, $data)
            : $data = [];

        unset($file_path, $file_name, $conf_file);
        return $data;
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
     * @param array  $values
     */
    public static function set(string $section, array $values): void
    {
        self::$pool[$section] = isset(self::$pool[$section])
            ? array_replace(self::$pool[$section], $values)
            : $values;

        unset($section, $values);
    }
}