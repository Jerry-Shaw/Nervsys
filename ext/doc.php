<?php

/**
 * Doc Extension
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

use core\handler\factory;

use core\parser\trustzone;

class doc extends factory
{
    //Define exclude paths
    public $exclude_path = ['core', 'ext'];

    /**
     * Shaw API CMD
     *
     * @param string $path
     *
     * @return array
     * @throws \ReflectionException
     */
    public function show_api(string $path = '/'): array
    {
        $api = [];

        //Refill path with app_path
        if ('' !== parent::$sys['app_path']) {
            $path = parent::$sys['app_path'] . DIRECTORY_SEPARATOR . $path;
        }

        //Redirect path related to ROOT
        $path = ROOT . trim($path, " \t\n\r\0\x0B\\/");

        //Get all php scripts
        $files = file::get_list($path, '*.php', true);

        //Collect valid classes
        foreach ($files as $item) {
            //Remove ROOT path
            $name = substr($item, strlen(ROOT));

            //Skip ROOT path
            if (false === strpos($name, DIRECTORY_SEPARATOR)) {
                continue;
            }

            //Get dirname
            $dir = strstr($name, DIRECTORY_SEPARATOR, true);

            //Skip paths or files in exclude path and ROOT path
            if (in_array($dir, $this->exclude_path, true)) {
                continue;
            }

            //Skip files NOT valid
            $script = file_get_contents($item);
            if (false === strpos($script, 'class') || false === strpos($script, '$tz')) {
                continue;
            }

            //Get TrustZone
            if (empty($trustzone = trustzone::init($class = parent::build_name(substr($name, 0, -4))))) {
                continue;
            }

            //Get public method
            $methods = parent::reflect_class($class)->getMethods(\ReflectionMethod::IS_PUBLIC);

            //Get base module name
            $module = strstr(substr($name, strlen(parent::$sys['app_path'])), DIRECTORY_SEPARATOR, true);

            //Save method list
            $api[$module] = $this->get_api_list($class, $methods, $trustzone);
        }

        //Build API CMD
        $api = $this->get_api_cmd($api);

        unset($path, $files, $item, $name, $dir, $script, $trustzone, $class, $methods, $module);
        return $api;
    }

    /**
     * Build DOC
     *
     * @param string $name
     *
     * @return array
     * @throws \ReflectionException
     */
    public function show_doc(string $name): array
    {
        $doc = [];

        //Refill path with app_path
        if ('' !== parent::$sys['app_path']) {
            $name = parent::$sys['app_path'] . $name;
        }

        //Refill CMD using "__construct"
        if (false === strpos($name, '-')) {
            $name .= '__construct';
        }

        //Get class & method
        list($class, $method) = explode('-', $name);

        //Build class name
        $class = parent::build_name($class);

        //Get method reflection
        $reflect_method = parent::reflect_method($class, $method);

        //Get comment string
        $comment_string = (string)$reflect_method->getDocComment();

        //Convert comment from string to array
        $comment_array = '' !== $comment_string ? explode("\n", $comment_string) : [];

        //Find API name
        $doc['name'] = $this->find_named_comment($comment_array, '@api');

        //Get parameters
        $params = $reflect_method->getParameters();

        //Build param info
        foreach ($params as $param) {
            $data = [];

            $data['name']    = $param->getName();
            $data['type']    = is_object($type = $param->getType()) ? $type->getName() : 'undefined';
            $data['require'] = !$param->isDefaultValueAvailable();

            if (!$data['require']) {
                $data['default'] = $param->getDefaultValue();
            }

            //Find param comment
            $data['comment'] = $this->find_param_comment($comment_array, $data['name']);

            //Add param info
            $doc['param'][] = $data;
        }

        //Get TrustZone
        $trustzone = $this->get_trustzone(parent::reflect_class($class));

        //Add TrustZone and return comment
        $doc['tz']   = $trustzone[$method] ?? '*';
        $doc['note'] = $this->find_named_comment($comment_array, '@return');

        unset($name, $class, $method, $reflect_method, $comment_string, $comment_array, $params, $param, $data, $type, $trustzone);
        return $doc;
    }

    /**
     * Get API CMD list
     *
     * @param string $class
     * @param array  $methods
     * @param array  $trustzone
     *
     * @return array
     */
    private function get_api_list(string $class, array $methods, array $trustzone): array
    {
        $api = [];

        //Get class name (remove "app_path")
        $class = '' !== parent::$sys['app_path'] ? substr($class, strlen(parent::$sys['app_path']) + 1) : ltrim($class, '\\');
        $class = strtr($class, DIRECTORY_SEPARATOR, '/');

        //Get API method
        foreach ($methods as $item) {
            //Get method name
            $name = $item->name;

            //Skip exclude method
            if (!in_array($name, $trustzone, true)) {
                continue;
            }

            //Get comment string
            $comment_string = (string)$item->getDocComment();

            //Convert comment from string to array
            $comment_array = '' !== $comment_string ? explode("\n", $comment_string) : [];

            //Save method
            $api[] = [
                'name'  => $this->find_named_comment($comment_array, '@api'),
                'value' => $class . '-' . $name
            ];
        }

        unset($class, $methods, $trustzone, $item, $name, $comment_string, $comment_array);
        return $api;
    }

    /**
     * Get CMD list
     *
     * @param array $api
     *
     * @return array
     */
    private function get_api_cmd(array $api): array
    {
        $key = 0;
        $cmd = [];

        foreach ($api as $dir => $item) {
            $cmd[$key]['module'] = $dir;
            $cmd[$key]['cmd']    = $item;

            ++$key;
        }

        unset($api, $key, $dir, $item);
        return $cmd;
    }

    /**
     * Get TrustZone
     *
     * @param \ReflectionClass $class
     *
     * @return array
     */
    private function get_trustzone(\ReflectionClass $class): array
    {
        //Get TrustZone
        $property = $class->getDefaultProperties();

        //TrustZone not open
        if (!isset($property['tz']) || empty($property['tz'])) {
            return [];
        }

        $trustzone = &$property['tz'];
        unset($property);

        //Rebuild TrustZone
        if (is_string($trustzone)) {
            $trustzone = false !== strpos($trustzone, ',') ? explode(',', $trustzone) : [$trustzone];

            $tmp = [];
            foreach ($trustzone as $item) {
                $tmp[$item] = '';
            }

            $trustzone = &$tmp;
            unset($tmp);
        }

        //Fetch param
        foreach ($trustzone as $key => $item) {
            if (isset($item['param'])) {
                $item = $item['param'];
            }

            if (is_array($item)) {
                $trustzone[$key] = implode(',', $item);
            }
        }

        unset($class, $key, $item);
        return $trustzone;
    }

    /**
     * Find param comment
     *
     * @param array  $comments
     * @param string $param
     *
     * @return string
     */
    private function find_param_comment(array $comments, string $param): string
    {
        $comment = '';

        foreach ($comments as $item) {
            if (false === strpos($item, '@param')) {
                continue;
            }

            if (false === $pos = strpos($item, '$' . $param)) {
                continue;
            }

            $comment = trim(substr($item, $pos + strlen($param) + 1));
            break;
        }

        unset($comments, $param, $item, $pos);
        return $comment;
    }

    /**
     * Find named comment
     *
     * @param array  $comments
     * @param string $name
     *
     * @return string
     */
    private function find_named_comment(array $comments, string $name): string
    {
        $comment = '';

        foreach ($comments as $item) {
            if (false === $pos = strpos($item, $name)) {
                continue;
            }

            $comment = trim(substr($item, $pos + strlen($name)));
            break;
        }

        unset($comments, $name, $item, $pos);
        return $comment;
    }
}