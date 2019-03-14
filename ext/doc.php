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

use core\system;

class doc extends system
{
    //Exclude method
    private static $exclude_method = [];

    //Exclude path or file
    const EXCLUDE_PATH = ['core', 'ext', 'api.php'];

    /**
     * Show API info
     *
     * @param string $path
     *
     * @return array
     */
    public static function show_api(string $path): array
    {
        //Get factory method
        self::$exclude_method = get_class_methods('core\\handler\\factory');

        //Fetch valid class
        $class = self::fetch_class(ROOT . trim($path, " \t\n\r\0\x0B\\/"));

        //Build reflections
        $reflect = self::build_info($class);

        unset($path, $class);
        return $reflect;
    }

    /**
     * Fetch valid class
     *
     * @param string $path
     *
     * @return array
     */
    public static function fetch_class(string $path): array
    {
        $class = [];

        //Get all php scripts
        $files = file::get_list($path, '*.php', true);

        //Collect valid classes
        foreach ($files as $item) {
            $value = substr($item, strlen(ROOT));

            $match = false !== strpos($value, DIRECTORY_SEPARATOR)
                ? strstr($value, DIRECTORY_SEPARATOR, true)
                : $value;

            //Skip files in exclude
            if (in_array($match, self::EXCLUDE_PATH, true)) {
                continue;
            }

            $script = file_get_contents($item);

            //Skip files NOT valid
            if (false === strpos($script, 'class') || false === strpos($script, '$tz')) {
                continue;
            }

            //Get class name
            $class[] = parent::build_name(substr($value, 0, -4));
        }

        unset($path, $files, $item, $value, $match, $script);
        return $class;
    }

    /**
     * Build class info
     *
     * @param array $class
     *
     * @return array
     */
    public static function build_info(array $class): array
    {
        $list = [];

        foreach ($class as $name) {
            try {
                //Build reflect
                $reflect = new \ReflectionClass($name);

                //Get TrustZone
                $property  = $reflect->getDefaultProperties();
                $trustzone = $property['tz'] ?? [];
                unset($property);

                if (empty($trustzone)) {
                    continue;
                }

                if (is_string($trustzone)) {
                    $trustzone = false !== strpos($trustzone, ',') ? explode(',', $trustzone) : [$trustzone];

                    $tmp = [];
                    foreach ($trustzone as $item) {
                        $tmp[$item] = [];
                    }

                    $trustzone = $tmp;
                    unset($tmp);
                }


                foreach ($trustzone as $key => $item) {
                    if (isset($item['param'])) {
                        $trustzone[$key] = $item['param'];
                    }
                }

                //Get public method
                $public_method = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

                //Get method
                $api_method = [];
                foreach ($public_method as $item) {
                    //Get method name
                    $method = $item->name;

                    //Skip exclude method
                    if (in_array($method, self::$exclude_method, true)) {
                        continue;
                    }

                    //Skip NOT in TrustZone
                    if (!isset($trustzone['*']) && !isset($trustzone[$method])) {
                        continue;
                    }

                    //Parse params
                    $value  = [];
                    $params = $item->getParameters();
                    foreach ($params as $param) {
                        $val = [];

                        $val['name']    = $param->getName();
                        $val['type']    = is_object($type = $param->getType()) ? $type->getName() : 'undefined';
                        $val['require'] = !$param->isDefaultValueAvailable();

                        if (!$val['require']) {
                            $val['default'] = $param->getDefaultValue();
                        }

                        $value[] = $val;
                    }

                    //Collect API info
                    $api_method[$method] = [
                        'tz'    => $trustzone[$method] ?? '',
                        'note'  => $item->getDocComment(),
                        'param' => $value
                    ];
                }

                $list[strtr($reflect->getName(), '\\', '/')] = $api_method;
            } catch (\Throwable $throwable) {
                continue;
            }
        }

        unset($class, $name, $reflect, $trustzone, $key, $item, $public_method, $api_method, $method, $value, $params, $param, $val, $type);
        return $list;
    }
}