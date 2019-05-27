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

class doc extends factory
{
    //Define exclude paths
    protected $exclude_path = ['core', 'ext'];

    /**
     * Add exclude path
     *
     * @param string $path
     */
    public function add_exclude(string $path): void
    {
        if ('' !== parent::$sys['app_path']) {
            $path = trim(parent::$sys['app_path'], " \t\n\r\0\x0B\\/") . DIRECTORY_SEPARATOR . $path;
        }

        if (!is_dir(ROOT . $path)) {
            return;
        }

        $this->exclude_path[] = strtr($path, '/', DIRECTORY_SEPARATOR);
    }

    /**
     * Get structure list
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_struct(): array
    {
        $struct = [];
        $path   = ROOT;

        //Refill path with app_path
        if ('' !== parent::$sys['app_path']) {
            $path .= trim(parent::$sys['app_path'], " \t\n\r\0\x0B\\/");
        }

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

            //Skip paths or files in exclude path settings
            foreach ($this->exclude_path as $exclude) {
                if (0 === strpos($name, $exclude)) {
                    continue 2;
                }
            }

            //Skip files NOT valid
            $script = file_get_contents($item);
            if (false === strpos($script, 'class') || false === strpos($script, '$tz')) {
                continue;
            }

            //Get class name
            $class = substr($name, 0, -4);

            //Reflect class
            $class_reflect = parent::reflect_class(parent::get_app_class(DIRECTORY_SEPARATOR . $class));

            //Check TrustZone
            if (empty($this->get_trustzone($class_reflect))) {
                continue;
            }

            //Process path
            $path_unit = explode(DIRECTORY_SEPARATOR, $class);

            //Remove app_path
            if ('' !== parent::$sys['app_path']) {
                array_shift($path_unit);
            }

            //Get file name
            $file_name = array_pop($path_unit);

            //Build path name
            $path_name = implode('/', $path_unit);

            //Get class comment
            $comment_string = (string)$class_reflect->getDocComment();

            //Convert comment from string to array
            $comment_array = '' !== $comment_string ? explode("\n", $comment_string) : [];

            //Save to struct
            $struct[$path_name][] = [
                'name'  => $this->find_named_comment($comment_array, '@api'),
                'value' => $file_name
            ];
        }

        unset($path, $files, $item, $name, $exclude, $script, $class, $class_reflect, $path_unit, $file_name, $path_name, $comment_string, $comment_array, $module);
        return $struct;
    }

    /**
     * Get API list from a class
     *
     * @param string $module
     * @param string $class
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_api(string $module, string $class): array
    {
        //Build full module name
        $module_name = '' !== parent::$sys['app_path']
            ? trim(parent::$sys['app_path'], " \t\n\r\0\x0B\\/") . '\\' . $module
            : $module;

        //Build class name
        $class_name = parent::get_app_class('\\' . $module_name . '\\' . $class);

        //Reflect class
        $class_reflect = parent::reflect_class($class_name);

        //Fetch TrustZone
        $trustzone = array_keys($this->get_trustzone($class_reflect));

        //Get public method
        $method_list = $class_reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

        //Get API list
        $list = $this->get_api_list($module . '/' . $class, $method_list, $trustzone);

        unset($module, $class, $module_name, $class_name, $class_reflect, $trustzone, $method_list);
        return $list;
    }

    /**
     * Build DOC
     *
     * @param string $api
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_doc(string $api): array
    {
        $doc = [];

        //Refill path with app_path
        if ('' !== parent::$sys['app_path']) {
            $api = parent::$sys['app_path'] . $api;
        }

        //Refill CMD using "__construct"
        if (false === strpos($api, '-')) {
            $api .= '__construct';
        }

        //Get class & method
        list($class, $method) = explode('-', $api);

        //Build class name
        $class = parent::get_app_class('/' . $class);

        //Get method reflection
        $reflect_method = parent::reflect_method($class, $method);

        //Get comment string
        $comment_string = (string)$reflect_method->getDocComment();

        //Convert comment from string to array
        $comment_array = '' !== $comment_string ? explode("\n", $comment_string) : [];

        //Get API CMD & DESC
        $doc['cmd']  = parent::get_app_cmd($api);
        $doc['desc'] = $this->find_named_comment($comment_array, '@api');

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

        unset($api, $class, $method, $reflect_method, $comment_string, $comment_array, $params, $param, $data, $type, $trustzone);
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