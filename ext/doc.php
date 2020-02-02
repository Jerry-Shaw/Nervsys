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

use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class doc
 *
 * @package ext
 */
class doc extends factory
{
    /** @var \core\lib\std\reflect $unit_reflect */
    private $unit_reflect;

    /** @var \core\lib\std\router $unit_router */
    private $unit_router;

    /**
     * doc constructor.
     */
    public function __construct()
    {
        /** @var \core\lib\std\reflect unit_reflect */
        $this->unit_reflect = \core\lib\stc\factory::build(reflect::class);

        /** @var \core\lib\std\router unit_router */
        $this->unit_router = \core\lib\stc\factory::build(router::class);
    }

    /**
     * Show API list
     *
     * @return array
     */
    public function show_api(): array
    {
        $struct = [];

        //Build app path
        $app_path = ROOT . DIRECTORY_SEPARATOR . APP_PATH . DIRECTORY_SEPARATOR;

        //Get all php scripts
        $files = file::get_list($app_path, '*.php', true);

        //Collect valid classes
        foreach ($files as $item) {
            //Skip files NOT valid
            $script = file_get_contents($item);

            if (false === strpos($script, 'class') || false === strpos($script, '$tz')) {
                continue;
            }

            //Get CMD class name
            $name = substr($item, strlen($app_path), -4);

            try {
                //Get full class name
                $class = $this->unit_router->get_cls($name);

                //Reflect class
                $class_reflect = $this->unit_reflect->get_class($class);

                //Get default properties
                $properties = $class_reflect->getDefaultProperties();

                //Skip class with tz NOT open
                if (empty($tz_data = isset($properties['tz']) ? (array)$properties['tz'] : [])) {
                    continue;
                }

                //Skip class with no public method
                if (empty($pub_func = $this->unit_reflect->get_method_list($class, \ReflectionMethod::IS_PUBLIC))) {
                    continue;
                }

                //Get method list
                $method_list = array_column($pub_func, 'name');

                //Get trust list
                $trust_list = !in_array('*', $tz_data, true)
                    ? array_intersect($tz_data, $method_list)
                    : $method_list;

                //Remove magic methods
                foreach ($trust_list as $key => $func) {
                    if (0 === strpos($func, '__')) {
                        unset($trust_list[$key]);
                    }
                }

                //Skip class with no trust method
                if (empty($trust_list)) {
                    continue;
                }

                //Get first sub-level dir name
                $dir_name = false !== strpos($name = strtr($name, '\\', '/'), '/')
                    ? strstr($name, '/', true)
                    : $name;

                //Save to struct
                $struct[$dir_name][] = [
                    'name'     => $this->find_named_comment((string)$class_reflect->getDocComment(), '@api'),
                    'value'    => $name,
                    'cmd_list' => $this->fill_cmd_list($name, $class, $trust_list)
                ];
            } catch (\Throwable $throwable) {
                unset($throwable);
            }
        }

        unset($app_path, $files, $item, $script, $name, $class, $class_reflect, $properties, $tz_data, $pub_func, $method_list, $trust_list, $key, $func, $dir_name);
        return $struct;
    }

    /**
     * Show API Doc
     *
     * @param string $cmd
     *
     * @return array
     * @throws \ReflectionException
     */
    public function show_doc(string $cmd): array
    {
        $doc = [];

        //Get class name & method
        [$name, $method] = explode('-', $cmd);

        //Build full class name
        $class = $this->unit_router->get_cls($name);

        //Get method reflection
        $reflect_method = $this->unit_reflect->get_method($class, $method);

        //Get comment string
        $comment_string = (string)$reflect_method->getDocComment();

        //Get doc name and return
        $doc['name']   = $this->find_named_comment($comment_string, '@api');
        $doc['return'] = $this->find_named_comment($comment_string, '@return');

        //Get param list
        $reflect_params = $this->unit_reflect->get_params($class, $method);

        //Build param info list
        foreach ($reflect_params as $reflect_param) {
            try {
                $data = [];

                //Get param info
                $param_info = $this->unit_reflect->get_param_info($reflect_param);

                $data['name'] = $param_info['name'];
                $data['type'] = $param_info['has_type'] ? $param_info['type'] : 'mixed';

                $data['require'] = !$param_info['has_default'];

                if ($param_info['has_default']) {
                    $data['default'] = $param_info['default'];
                }

                $data['comment'] = $this->find_named_comment($comment_string, '$' . $data['name']);

                //Add param info
                $doc['param'][] = $data;
            } catch (\Throwable $throwable) {
                unset($throwable);
            }
        }

        unset($cmd, $name, $method, $class, $reflect_method, $comment_string, $reflect_params, $reflect_param, $data, $param_info);
        return $doc;
    }

    /**
     * Fill CMD name
     *
     * @param string $name
     * @param string $class
     * @param array  $methods
     *
     * @return array
     * @throws \ReflectionException
     */
    private function fill_cmd_list(string $name, string $class, array $methods): array
    {
        $cmd_list = [];

        foreach ($methods as $method) {
            $reflect_method = $this->unit_reflect->get_method($class, $method);

            $cmd_list[] = [
                'name'  => $this->find_named_comment((string)$reflect_method->getDocComment(), '@api'),
                'value' => $name . '-' . $method
            ];
        }

        unset($name, $class, $methods, $method, $reflect_method);
        return $cmd_list;
    }

    /**
     * Find named comment
     *
     * @param string $comment
     * @param string $name
     *
     * @return string
     */
    private function find_named_comment(string $comment, string $name): string
    {
        //Name NOT found
        if (false === $row_start = strpos($comment, $name)) {
            return 'NOT found';
        }

        //Find comment end
        if (false === $end = strpos($comment, "\n", $row_start)) {
            $end = strlen($comment);
        }

        //Find comment start
        if (false === $start = strpos($comment, ' ', $row_start)) {
            $start = &$row_start;
        }

        //Start overflow
        if (++$start > $end) {
            return 'NOT found';
        }

        //Get comment text
        $text = trim(substr($comment, $start, $end - $start));

        unset($comment, $name, $row_start, $end, $start);
        return $text;
    }
}