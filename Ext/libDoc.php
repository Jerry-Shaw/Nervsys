<?php

/**
 * API Document Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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
 * Class libDoc
 *
 * @package Ext
 */
class libDoc extends Factory
{
    private App    $app;
    private string $api_path;

    /**
     * libDoc constructor.
     */
    public function __construct()
    {
        $this->app      = App::new();
        $this->api_path = $this->app->root_path . DIRECTORY_SEPARATOR . $this->app->api_path;
    }

    /**
     * Get directory structure in api_path
     *
     * @return array
     */
    public function getStruct(): array
    {
        $dir_handler = opendir($this->api_path);

        if (false === $dir_handler) {
            return [];
        }

        $dir_list = [];

        while (false !== ($dir_name = readdir($dir_handler))) {
            if (false === strpos($dir_name, '.')) {
                $dir_list[] = $dir_name;
            }
        }

        closedir($dir_handler);

        unset($dir_handler, $dir_name);
        return $dir_list;
    }

    /**
     * Get all *.php in api_path or struct path
     *
     * @param string $path_name
     *
     * @return array
     */
    public function getEntryList(string $path_name = ''): array
    {
        $scan_path = '' !== $path_name ? $this->api_path . DIRECTORY_SEPARATOR . $path_name : $this->api_path;
        $file_list = libFile::new()->getList($scan_path, '*.php', true);

        $root_len = strlen($this->api_path . DIRECTORY_SEPARATOR);

        foreach ($file_list as &$value) {
            $value = strtr(substr($value, $root_len, -4), '\\', '/');
        }

        unset($path_name, $scan_path, $root_len, $value);
        return $file_list;
    }

    /**
     * Get all API list in all/one module class(es)
     * Parent APIs will be ignored
     *
     * @param string $module_name
     *
     * @return array
     */
    public function getApiList(string $module_name = ''): array
    {
        $get_fn = function (string $class): array
        {
            if (empty($fn_list = get_class_methods($class))) {
                return [];
            }

            if (false !== ($parent = get_parent_class($class))) {
                $fn_list = array_diff($fn_list, get_class_methods($parent));
            }

            unset($class, $parent);
            return $fn_list;
        };

        $api_list = [];
        $root_len = strlen($this->app->root_path);
        $api_len  = strlen($this->app->api_path) + 2;

        $module_list = '' !== $module_name ? [$module_name] : $this->getEntryList();

        foreach ($module_list as $value) {
            $value = strtr(($this->api_path . DIRECTORY_SEPARATOR . $value), '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
            $class = strtr(substr($value, $root_len), '/', '\\');

            if (empty($fn_list = $get_fn($class))) {
                continue;
            }

            $api_name = substr(strtr($class, '\\', '/'), $api_len);

            foreach ($fn_list as $fn_name) {
                if (0 === strpos($fn_name, '__')) {
                    continue;
                }

                $fn_info = [];
                $fn_doc  = $this->getDoc($class, $fn_name);

                $fn_info['api']    = $api_name . '/' . $fn_name;
                $fn_info['name']   = $this->getName($fn_doc);
                $fn_info['params'] = $this->getParamList($fn_doc);
                $fn_info['return'] = $this->getReturn($fn_doc);

                $api_list[] = $fn_info;
            }
        }

        unset($module_name, $get_fn, $root_len, $api_len, $module_list, $value, $class, $fn_list, $api_name, $fn_name, $fn_info);
        return $api_list;
    }

    /**
     * Get raw doc comment string
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    public function getDoc(string $class, string $method): string
    {
        try {
            $doc = (new \ReflectionMethod($class, $method))->getDocComment();

            if (false === $doc) {
                return '';
            }

            unset($class, $method);
            return $doc;
        } catch (\Throwable $throwable) {
            unset($class, $method, $throwable);
            return '';
        }
    }

    /**
     * Get API comment name (first rows before "@")
     *
     * @param string $doc
     *
     * @return string
     */
    public function getName(string $doc): string
    {
        $start  = 0;
        $result = '';

        while (false !== ($pos = strpos($doc, "\n", $start))) {
            $line = substr($doc, $start, $pos - $start);
            $line = ltrim(trim($line), '/* ');

            if ('' === $line) {
                $start = $pos + 1;
                continue;
            }

            if (0 === strpos($line, '@')) {
                break;
            }

            $result .= '' === $result ? $line : ', ' . $line;
            $start  = $pos + 1;
        }

        unset($doc, $start, $pos, $line);
        return $result;
    }

    /**
     * Get param list from comment
     *
     * @param string $doc
     *
     * @return array
     */
    public function getParamList(string $doc): array
    {
        $start  = 0;
        $result = [];

        while (false !== ($pos = strpos($doc, "\n", $start))) {
            $line = substr($doc, $start, $pos - $start);
            $line = ltrim(trim($line), '/* ');

            if ('' === $line || 0 !== strpos($line, '@param')) {
                $start = $pos + 1;
                continue;
            }

            $result[] = substr($line, 7);
            $start    = $pos + 1;
        }

        unset($doc, $start, $pos, $line);
        return $result;
    }

    /**
     * Get return content from comment
     *
     * @param string $doc
     *
     * @return string
     */
    public function getReturn(string $doc): string
    {
        $result = '';

        if (false === ($start = strpos($doc, '@return'))) {
            return 'void';
        }

        $start += 7;

        while (false !== ($pos = strpos($doc, "\n", $start))) {
            $line = substr($doc, $start, $pos - $start);
            $line = ltrim(trim($line), '/* ');

            if ('' === $line) {
                $start = $pos + 1;
                continue;
            }

            if (0 === strpos($line, '@')) {
                break;
            }

            $result .= '' === $result ? $line : ', ' . $line;
            $start  = $pos + 1;
        }

        unset($doc, $start, $pos, $line);
        return $result;
    }
}