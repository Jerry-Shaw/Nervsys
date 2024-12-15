<?php

/**
 * Zip Extension
 *
 * Copyright 2020-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\App;

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

    public string $store_path  = 'zipFile';
    public array  $target_file = [];

    /**
     * libZip constructor
     *
     * @throws \ReflectionException
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
        $this->store_path = $path;

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

        $zipArchive = new \ZipArchive();
        $zip_path   = $this->store_path . DIRECTORY_SEPARATOR . $filename . '.zip';

        try {
            $errno = $zipArchive->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            if (true !== $errno) {
                throw new \Exception('Zip failed!', $errno);
            }

            foreach ($this->target_file as $file_path) {
                $file_path = realpath($file_path);
                $file_name = basename($file_path);

                is_dir($file_path)
                    ? $this->zipDir($zipArchive, $file_path, $file_name)
                    : $this->zipFile($zipArchive, $file_path, $file_name);
            }

            unset($errno, $file_path, $file_name);

            $result = ['errno' => 0, 'path' => $zip_path];
        } catch (\Throwable $throwable) {
            $result = $this->getError($throwable->getCode());
            unset($throwable);
        }

        $zipArchive->close();
        $this->target_file = [];

        unset($filename, $zipArchive, $zip_path);
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
     * @param string $name
     *
     * @return string
     */
    private function getEntryName(string $path, string $name): string
    {
        return strtr(substr($path, strpos($path, $name)), '\\', '/');
    }

    /**
     * Zip a file
     *
     * @param \ZipArchive $zipArchive
     * @param string      $path
     * @param string      $name
     */
    private function zipFile(\ZipArchive $zipArchive, string $path, string $name): void
    {
        $path = strtr($path, '\\', '/');
        $zipArchive->addFile($path, $this->getEntryName($path, $name));

        unset($zipArchive, $path, $name);
    }

    /**
     * Zip a folder
     *
     * @param \ZipArchive $zipArchive
     * @param string      $path
     * @param string      $name
     *
     * @throws \Exception
     */
    private function zipDir(\ZipArchive $zipArchive, string $path, string $name): void
    {
        if (false === ($dir = opendir($path))) {
            throw new \Exception('Open "' . $path . '" failed!', \ZipArchive::ER_READ);
        }

        $zipArchive->addEmptyDir($this->getEntryName($path, $name));

        while (false !== ($file = readdir($dir))) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $file_path = realpath($path . DIRECTORY_SEPARATOR . $file);

            is_dir($file_path)
                ? $this->zipDir($zipArchive, $file_path, $name)
                : $this->zipFile($zipArchive, $file_path, $name);
        }

        unset($zipArchive, $path, $name, $dir, $file, $file_path);
    }

    /**
     * Create path
     *
     * @param string $path
     */
    private function mkPath(string $path): void
    {
        if (!is_dir($path)) {
            try {
                mkdir($path, 0777, true);
                chmod($path, 0777);
            } catch (\Throwable) {
                //Dir already exists
            }
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
            'errno'   => $errno,
            'message' => self::ERRNO[$errno] ?? 'Zip failed!'
        ];
    }
}