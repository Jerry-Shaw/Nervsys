<?php

/**
 * File I/O Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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

namespace core\ctrl;

class file
{
    /**
     * Get the extension of a file
     *
     * @param string $path
     *
     * @return string
     */
    public static function get_ext(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ('' !== $ext && 1 === preg_match('/[A-Z]/', $ext)) $ext = strtolower($ext);
        unset($path);
        return $ext;
    }

    /**
     * Check and create the directory if not exists, return a relative path
     *
     * @param string $path
     *
     * @return string
     */
    public static function get_path(string $path): string
    {
        $real_path = FILE_PATH;
        if ('' !== $path) {
            if (false !== strpos($path, '..')) $path = str_replace('..', '.', $path);//Parent directory is not allowed
            if (false !== strpos($path, '\\')) $path = str_replace('\\', '/', $path);//Get a formatted url path with '/'
            $real_path .= $path;
            if (!is_dir($real_path)) mkdir($real_path, 0664, true);
        }
        $file_path = is_readable($real_path) ? $path . '/' : ':';
        unset($path, $real_path);
        return $file_path;
    }

    /**
     * Get a list of files in a directory or recursively
     * Target extension can be passed by $pattern parameter
     *
     * @param string $path
     * @param string $pattern
     * @param bool $recursive
     *
     * @return array
     */
    public static function get_list(string $path, string $pattern = '*', bool $recursive = false): array
    {
        $list = [];
        $path = realpath($path);
        if (false !== $path) {
            $path .= '/';
            $list = glob($path . $pattern);
            if ($recursive) {
                $dir_list = glob($path . '*');
                foreach ($dir_list as $dir) {
                    if (is_dir($dir)) $list = array_merge($list, self::get_list($dir, $pattern, true));
                    else continue;
                }
                unset($dir);
            }
        }
        unset($path, $pattern, $recursive);
        return $list;
    }
}