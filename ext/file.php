<?php

/**
 * File I/O Extension
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

namespace ext;

class file
{
    /**
     * Get file extension (in lowercase)
     *
     * @param string $path
     *
     * @return string
     */
    public static function get_ext(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ('' !== $ext) $ext = strtolower($ext);
        unset($path);
        return $ext;
    }

    /**
     * Check & create directory (Related to "$root")
     *
     * @param string $path
     * @param string $root
     * @param int    $mode
     *
     * @return string
     */
    public static function get_path(string $path, string $root = ROOT, int $mode = 0776): string
    {
        //Parent directory is not allowed
        if (false !== strpos($path, '..')) $path = str_replace('..', '', $path);
        //Format path with '/'
        if (false !== strpos($path, '\\')) $path = strtr($path, '\\', '/');

        //Trim "/"
        $path = trim($path, '/');

        //Return "/" when path is empty
        if ('' === $path) return is_readable($root) ? '/' : '';

        //Add "/"
        $path = '/' . $path;

        //Create directories
        $dir = $root . $path;
        if (!is_dir($dir)) {
            //Create directory recursively
            mkdir($dir, $mode, true);
            //Set permissions to path
            chmod($dir, $mode);
        }

        //Check path property
        $path = is_readable($dir) ? $path . '/' : '';

        unset($root, $mode, $dir);
        return $path;
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

        //Get file list
        $path .= '/';
        $list = glob($path . $pattern, GLOB_NOSORT | GLOB_BRACE);

        //Return list on non-recursive
        if (!$recursive) return $list;

        //Get file list recursively
        $dirs = glob($path . '*', GLOB_NOSORT | GLOB_ONLYDIR);
        foreach ($dirs as $dir) $list = array_merge($list, self::get_list($dir, $pattern, true));

        unset($path, $pattern, $recursive, $dirs, $dir);
        return $list;
    }
}