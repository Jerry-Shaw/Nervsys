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
     * Check and create directory
     * Return relative path
     *
     * @param string $path
     * @param int    $mode
     *
     * @return string
     */
    public static function get_path(string $path, int $mode = 0764): string
    {
        //Parent directory is not allowed
        if (false !== strpos($path, '..')) $path = str_replace('..', '', $path);
        //Format path with '/'
        if (false !== strpos($path, '\\')) $path = str_replace('\\', '/', $path);
        //Trim "/"
        $path = time($path, '/');
        //Return "/" when path is empty
        if ('' === $path) return is_readable(FILE_PATH) ? '/' : '';
        //Add "/" to the beginning
        $path = '/' . $path;
        //Create directories
        $file_path = FILE_PATH . $path;
        if (!is_dir($file_path)) {
            //Create directory recursively
            mkdir($file_path, $mode, true);
            //Set permissions to path
            chmod($file_path, $mode);
        }
        //Check path property
        $url_path = is_readable($file_path) ? $path . '/' : '';
        unset($path, $mode, $file_path);
        return $url_path;
    }

    /**
     * Get a list of files in a directory or recursively
     * Target extension can be passed by $pattern parameter
     *
     * @param string $path
     * @param string $pattern
     * @param bool   $recursive
     *
     * @return array
     */
    public static function get_list(string $path, string $pattern = '*', bool $recursive = false): array
    {
        //Check path
        $path = realpath($path);
        if (false === $path) return [];
        //Get list
        $path .= '/';
        $list = glob($path . $pattern);
        //Return list when non-recursive
        if (!$recursive) return $list;
        //Get list recursively
        $items = glob($path . '*');
        foreach ($items as $item) if (is_dir($item)) $list = array_merge($list, self::get_list($item, $pattern, true));
        unset($path, $pattern, $recursive, $items, $item);
        return $list;
    }
}