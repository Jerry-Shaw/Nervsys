<?php

/**
 * File I/O Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\App;

/**
 * Class libFile
 *
 * @package Ext
 */
class libFile extends Factory
{
    /**
     * Get file extension (in lowercase)
     *
     * @param string $path
     *
     * @return string
     */
    public function getExt(string $path): string
    {
        if ('' !== $ext = pathinfo($path, PATHINFO_EXTENSION)) {
            $ext = strtolower($ext);
        }

        unset($path);
        return $ext;
    }

    /**
     * Check & create directory (root based)
     *
     * @param string $path
     * @param string $root
     *
     * @return string
     */
    public function mkPath(string $path, string $root = ''): string
    {
        //Define root
        $root = App::new()->getRootPath($root);

        //Get clean path
        $path = strtr($path, '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
        $path = trim($path, DIRECTORY_SEPARATOR);

        //Return root path
        if ('' === $path) {
            return $root . DIRECTORY_SEPARATOR;
        }

        //Create directories
        if (!is_dir($dir = $root . DIRECTORY_SEPARATOR . $path)) {
            mkdir($dir, 0777, true);
            chmod($dir, 0777);
        }

        //Check path property
        $path = (is_readable($dir) ? $path : $root) . DIRECTORY_SEPARATOR;

        unset($root, $dir);
        return $path;
    }

    /**
     * Get a list of files in a directory or recursively
     *
     * @param string $path
     * @param string $pattern
     * @param bool   $recursive
     *
     * @return array
     */
    public function getList(string $path, string $pattern = '*', bool $recursive = false): array
    {
        if (false === $path_name = realpath($path)) {
            return [];
        }

        $path_name .= DIRECTORY_SEPARATOR;
        $file_list = glob($path_name . $pattern, GLOB_NOSORT | GLOB_BRACE);

        //Return list on non-recursive
        if (!$recursive) {
            unset($path, $pattern, $recursive, $path_name);
            return $file_list;
        }

        $dir_list = glob($path_name . '*', GLOB_NOSORT | GLOB_ONLYDIR);

        //Get file list recursively
        foreach ($dir_list as $dir) {
            $file_list = array_merge($file_list, $this->getList($dir, $pattern, true));
        }

        unset($path, $pattern, $recursive, $path_name, $dir_list, $dir);
        return $file_list;
    }

    /**
     * Copy dir to path
     *
     * @param string $src
     * @param string $dst
     *
     * @return int
     */
    public function copyDir(string $src, string $dst): int
    {
        $copied = 0;

        if (!is_dir($src) || false === ($dir = opendir($src))) {
            return -1;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
            chmod($dir, 0777);
        }

        while (false !== ($file = readdir($dir))) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $src_path = $src . DIRECTORY_SEPARATOR . $file;
            $dst_path = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($src_path)) {
                $copied += $this->copyDir($src_path, $dst_path);
                continue;
            }

            if (copy($src_path, $dst_path)) {
                ++$copied;
            } else {
                return -1;
            }
        }

        unset($src, $dst, $dir, $file, $src_path, $dst_path);
        return $copied;
    }

    /**
     * Delete dir by path
     *
     * @param string $path
     *
     * @return int
     */
    public function delDir(string $path): int
    {
        $removed = 0;

        if (!is_dir($path) || false === ($dir = opendir($path))) {
            return -1;
        }

        while (false !== ($file = readdir($dir))) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $file_path = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($file_path)) {
                $removed += $this->delDir($file_path);
                continue;
            }

            if (unlink($file_path)) {
                ++$removed;
            } else {
                return -1;
            }
        }

        closedir($dir);
        rmdir($path);

        unset($path, $dir, $file, $file_path);
        return $removed;
    }
}