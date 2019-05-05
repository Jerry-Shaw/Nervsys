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
    //Define excludes
    public $exclude_func = [];
    public $exclude_path = ['core', 'ext'];

    /**
     * doc constructor.
     */
    public function __construct()
    {
        //Read all functions from parent class
        $this->exclude_func = get_class_methods(get_parent_class(__CLASS__));
    }

    /**
     * Show API CMD
     *
     * @param string $path
     *
     * @return array
     */
    public function show_api(string $path): array
    {
        //API
        $api = [];

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

            //Get opened API name
            if (!empty($method = $this->get_opened_api($class = parent::build_name(substr($name, 0, -4))))) {
                $api[$dir][strtr(ltrim($class, '\\'), DIRECTORY_SEPARATOR, '/')] = $method;
            }
        }

        //Build CMD for API
        $api = $this->get_api_cmd($api);

        unset($path, $files, $item, $name, $dir, $script, $method, $class);
        return $api;
    }

    /**
     * Build DOC
     *
     * @param string $command
     *
     * @return array
     * @throws \ReflectionException
     */
    public function show_doc(string $command): array
    {
        //DOC
        $doc = $data = [];

        //Fill CMD
        if (false === strpos($command, '-')) {
            $command .= '__construct';
        }

        //Get class & method
        list($class, $method) = explode('-', $command);

        //Build class name
        $class = parent::build_name($class);

        //Build class reflection
        $reflect_class = parent::reflect_class($class);

        //Get method reflection
        $reflect_method = parent::reflect_method($class, $method);

        //Get TrustZone
        $trustzone = $this->get_trustzone($reflect_class);

        //Get parameters
        $params = $reflect_method->getParameters();

        //Build DOC
        $doc['tz']   = $trustzone[$method] ?? '';
        $doc['note'] = (string)$reflect_method->getDocComment();

        //Build param info
        foreach ($params as $param) {
            $data['name']    = $param->getName();
            $data['type']    = is_object($type = $param->getType()) ? $type->getName() : 'undefined';
            $data['require'] = !$param->isDefaultValueAvailable();

            if (!$data['require']) {
                $data['default'] = $param->getDefaultValue();
            }

            //Add param info
            $doc['param'][] = $data;
        }

        unset($command, $data, $class, $method, $reflect_class, $reflect_method, $trustzone, $params, $param);
        return $doc;
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

            //Build CMD
            foreach ($item as $class => $method) {
                $cmd[$key]['cmd'][] = array_map(
                    static function (string $item) use ($class): string
                    {
                        return parent::get_app_cmd($class . '-' . $item);
                    }, $method
                );
            }

            ++$key;
        }

        unset($api, $key, $dir, $item, $class, $method);
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

            $trustzone = $tmp;
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
     * Get opened methods
     *
     * @param string $class
     *
     * @return array
     */
    private function get_opened_api(string $class): array
    {
        //API
        $api = [];

        try {
            //Build reflection
            $reflect = parent::reflect_class($class);

            //Get TrustZone
            $trustzone = $this->get_trustzone($reflect);

            //Get public method
            $methods = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

            //Get API method
            foreach ($methods as $item) {
                //Get method name
                $method = $item->name;

                //Skip exclude method
                if (in_array($method, $this->exclude_func, true)) {
                    continue;
                }

                //Skip NOT in TrustZone
                if (!isset($trustzone['*']) && !isset($trustzone[$method]) && '__construct' !== $method) {
                    continue;
                }

                //Save method
                $api[] = $method;
            }
        } catch (\Throwable $throwable) {
            $api[] = [$throwable->getMessage()];
        }

        unset($class, $reflect, $trustzone, $item, $methods, $method);
        return $api;
    }
}