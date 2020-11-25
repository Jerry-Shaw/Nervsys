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
use Core\Lib\Router;
use Core\Reflect;

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
     * Factory APIs will be ignored
     *
     * @param string $c_name
     *
     * @return array
     */
    public function getApiList(string $c_name = ''): array
    {
        $api_list = [];
        $root_len = strlen($this->app->root_path);
        $api_len  = strlen($this->app->api_path) + 2;

        $module_list = '' !== $c_name ? [$c_name] : $this->getEntryList();

        foreach ($module_list as $value) {
            $value = strtr(($this->api_path . DIRECTORY_SEPARATOR . $value), '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);
            $class = strtr(substr($value, $root_len), '/', '\\');

            if (empty($fn_list = $this->getFnList($class))) {
                continue;
            }

            $api_list[] = $this->buildMethodData($class, $api_len, $fn_list);
        }

        unset($c_name, $root_len, $api_len, $module_list, $value, $class, $fn_list);
        return $api_list;
    }

    /**
     * Get raw comment string from c
     *
     * @param string $c
     *
     * @return string
     * @throws \ReflectionException
     */
    public function getComment(string $c): string
    {
        $cmd_group = Router::new()->parse($c);

        if (empty($cmd_group['cgi'])) {
            return '';
        }

        $cmd_group = current($cmd_group['cgi']);
        $comment   = $this->getDoc(new \ReflectionMethod($cmd_group[0], $cmd_group[1]));

        unset($c, $cmd_group);
        return $comment;
    }

    /**
     * Build param info data
     *
     * @param \ReflectionMethod $method
     * @param string            $fn_doc
     *
     * @return array
     * @throws \ReflectionException
     */
    private function buildParamData(\ReflectionMethod $method, string $fn_doc): array
    {
        $param_data = [];
        $reflect    = Reflect::new();
        $param_doc  = $this->getParamList($fn_doc);
        $param_list = $method->getParameters();

        foreach ($param_list as $param_reflect) {
            $param_info         = $reflect->getParamInfo($param_reflect);
            $param_info['desc'] = $param_doc[$param_info['name']] ?? '';
            $param_data[]       = $param_info;
        }

        unset($method, $fn_doc, $reflect, $param_doc, $param_list, $param_reflect, $param_info);
        return $param_data;
    }

    /**
     * Build method info data
     *
     * @param string $class
     * @param int    $api_len
     * @param array  $fn_list
     *
     * @return array
     */
    private function buildMethodData(string $class, int $api_len, array $fn_list): array
    {
        $api_list = [];
        $api_name = substr(strtr($class, '\\', '/'), $api_len);

        foreach ($fn_list as $fn_name) {
            if (0 === strpos($fn_name, '__')) {
                continue;
            }

            try {
                $method  = new \ReflectionMethod($class, $fn_name);
                $fn_doc  = $this->getDoc($method);
                $fn_info = [];

                $fn_info['api']    = $api_name . '/' . $fn_name;
                $fn_info['name']   = $this->getName($fn_doc);
                $fn_info['return'] = $this->getReturn($fn_doc);
                $fn_info['params'] = $this->buildParamData($method, $fn_doc);

                $api_list[] = $fn_info;
            } catch (\Throwable $throwable) {
                unset($throwable);
                continue;
            }
        }

        unset($class, $api_len, $fn_list, $api_name, $fn_name, $method, $fn_doc, $fn_info);
        return $api_list;
    }

    /**
     * Get raw doc comment string
     *
     * @param \ReflectionMethod $method
     *
     * @return string
     */
    private function getDoc(\ReflectionMethod $method): string
    {
        $doc = $method->getDocComment();

        if (false === $doc) {
            return '';
        }

        unset($method);
        return $doc;
    }

    /**
     * Get API comment name (first rows before "@")
     *
     * @param string $comment
     *
     * @return string
     */
    private function getName(string $comment): string
    {
        return $this->getTagInfo($comment, 0);
    }

    /**
     * Get return content from comment
     *
     * @param string $comment
     *
     * @return string
     */
    private function getReturn(string $comment): string
    {
        if (false === ($start = strpos($comment, '@return'))) {
            return 'void';
        }

        $result = $this->getTagInfo($comment, $start + 7);

        unset($comment, $start);
        return $result;
    }

    /**
     * Get function list from a class
     *
     * @param string $class
     *
     * @return array
     */
    private function getFnList(string $class): array
    {
        if (empty($fn_list = get_class_methods($class))) {
            return [];
        }

        $fn_list = array_diff($fn_list, get_class_methods(Factory::class));

        unset($class);
        return $fn_list;
    }

    /**
     * Get param list from comment
     *
     * @param string $comment
     *
     * @return array
     */
    private function getParamList(string $comment): array
    {
        $start  = 0;
        $result = [];

        while (false !== ($pos = strpos($comment, "\n", $start))) {
            $line = substr($comment, $start, $pos - $start);
            $line = ltrim(trim($line), '/* ');

            $start = $pos + 1;

            if ('' === $line || 0 !== strpos($line, '@param') || false === ($p_start = strpos($line, '$'))) {
                continue;
            }

            if (false !== ($p_end = strpos($line, ' ', $p_start))) {
                $result[substr($line, $p_start + 1, $p_end - $p_start)] = trim(substr($line, $p_end + 1));
            } else {
                $result[substr($line, $p_start + 1)] = '';
            }
        }

        unset($comment, $start, $pos, $line, $p_start, $p_end);
        return $result;
    }

    /**
     * Get info by comment tag
     *
     * @param string $comment
     * @param int    $start
     *
     * @return string
     */
    private function getTagInfo(string $comment, int $start): string
    {
        $result = '';

        while (false !== ($pos = strpos($comment, "\n", $start))) {
            $line = substr($comment, $start, $pos - $start);
            $line = ltrim(trim($line), '/* ');

            $start = $pos + 1;

            if ('' === $line) {
                continue;
            }

            if (0 === strpos($line, '@')) {
                break;
            }

            $result .= '' === $result ? $line : ', ' . $line;
        }

        unset($comment, $start, $pos, $line);
        return $result;
    }
}