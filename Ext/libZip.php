<?php

/**
 * Zip Extension
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
 * Class libZip
 *
 * @package Ext
 */
class libZip extends Factory
{
    //Error message
    const ERRNO = [
        \ZipArchive::ER_EXISTS => 'File already exists.',
        \ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
        \ZipArchive::ER_INVAL  => 'Invalid argument.',
        \ZipArchive::ER_MEMORY => 'Malloc failure.',
        \ZipArchive::ER_NOENT  => 'No such file.',
        \ZipArchive::ER_NOZIP  => 'Not a zip archive.',
        \ZipArchive::ER_OPEN   => 'Can\'t open file.',
        \ZipArchive::ER_READ   => 'Read error.',
        \ZipArchive::ER_SEEK   => 'Seek error.'
    ];

    public \ZipArchive $zipArchive;

    public string $store_path  = 'zipFile';
    public array  $target_file = [];

    /**
     * libZip constructor.
     */
    public function __construct()
    {
        $this->store_path = App::new()->root_path . DIRECTORY_SEPARATOR . $this->store_path;
    }

    /**
     * Set store path
     *
     * @param string $path
     *
     * @return $this
     */
    public function setStorePath(string $path): self
    {
        $this->store_path = &$path;

        unset($path);
        return $this;
    }

    /**
     * Add file/folder path to target list
     *
     * @param string $path
     *
     * @return $this
     */
    public function add(string $path): self
    {
        $this->target_file[] = $path;

        unset($path);
        return $this;
    }

    /**
     * Zip to file
     *
     * @param string $filename
     *
     * @return array
     */
    public function zipTo(string $filename): array
    {
        $this->mkPath($this->store_path);

        $this->zipArchive = new \ZipArchive();

        $zip_path = $this->store_path . DIRECTORY_SEPARATOR . $filename . '.zip';

        $target_files = $this->target_file;
        try {
            $errno = $this->zipArchive->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if (true !== $errno) {
                throw new \Exception('Zip failed!', $errno);
            }

            foreach ($target_files as $path) {
                $base_path = basename($path);
                is_dir($path) ? $this->zipDir($path, $base_path) : $this->zipFile($path, $base_path);
            }

            unset($errno, $base_path);

            $result = ['errno' => 0, 'path' => &$zip_path];
        } catch (\Throwable $throwable) {
            $result = $this->getError($throwable->getCode());
            unset($throwable);
        }

        $this->zipArchive->close();

        unset($filename, $zip_path, $path);
        return $result;
    }

    /**
     * Extract zip to path
     *
     * @param string $file
     * @param string $to
     *
     * @return bool
     * @throws \Exception
     */
    public function unzip(string $file, string $to): bool
    {
        $zipArchive = new \ZipArchive();

        if (true !== $zipArchive->open($file)) {
            throw new \Exception('Open "' . $file . '" failed!', E_USER_ERROR);
        }

        $this->mkPath($to);

        $result = $zipArchive->extractTo($to);
        $zipArchive->close();

        unset($file, $to, $zipArchive);
        return $result;
    }

    /**
     * Get entry name inside zip file
     *
     * @param string $path
     * @param string $base_path
     *
     * @return string
     */
    private function getEntryName(string $path, string $base_path): string
    {
        return substr($path, strpos($path, $base_path));
    }

    /**
     * Zip a file
     *
     * @param string $path
     * @param string $base_path
     */
    private function zipFile(string $path, string $base_path): void
    {
        $path = strtr($path, '\\', '/');

        $this->zipArchive->addFile($path, $this->getEntryName($path, $base_path));

        unset($path, $base_path);
    }

    /**
     * Zip a folder
     *
     * @param string $path
     * @param string $base_path
     *
     * @throws \Exception
     */
    private function zipDir(string $path, string $base_path): void
    {
        if (false === ($dir = opendir($path))) {
            throw new \Exception('Open "' . $path . '" failed!', \ZipArchive::ER_READ);
        }

        $this->zipArchive->addEmptyDir($this->getEntryName($path, $base_path));

        while (false !== ($file = readdir($dir))) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $file_path = $path . DIRECTORY_SEPARATOR . $file;

            is_dir($file_path) ? $this->zipDir($file_path, $base_path) : $this->zipFile($file_path, $base_path);
        }

        unset($path, $base_path, $dir, $file, $file_path);
    }

    /**
     * Create path
     *
     * @param string $path
     */
    private function mkPath(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            chmod($path, 0777);
        }

        unset($path);
    }

    /**
     * Get error message
     *
     * @param int $errno
     *
     * @return array
     */
    private function getError(int $errno): array
    {
        return [
            'errno'   => &$errno,
            'message' => self::ERRNO[$errno] ?? 'Zip failed!'
        ];
    }
}
