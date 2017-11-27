<?php

/**
 * cgi Router Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace core\ctr\router;

use \core\ctr\router as router;

class cgi extends router
{
    /**
     * Run CGI Router
     */
    public static function run(): void
    {
        //Prepare data
        if ('' === parent::$cmd) self::get_data();
        //Parse cmd data
        self::parse_cmd();
        //Execute cmd
        self::execute_cmd();
    }

    /**
     * Get CGI data
     */
    private static function get_data(): void
    {
        //Get data from HTTP POST / GET / REQUEST
        $data = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : $_REQUEST);
        if (!empty($data)) {
            parent::$data = &$data;
            unset($data);
            if (self::get_cmd()) {
                if (!empty($_FILES)) parent::$data = array_merge(parent::$data, $_FILES);
                parent::build_struct();
            }
            return;
        }

        //Get data from raw input stream
        $input = file_get_contents('php://input');
        if (false === $input) return;

        //Decode data in JSON
        $json = json_decode($input, true);
        if (is_array($json) && !empty($json)) {
            parent::$data = &$json;
            unset($input, $json);
            if (self::get_cmd()) parent::build_struct();
            return;
        }
    }

    /**
     * Get cmd value from data
     *
     * @return bool
     */
    private static function get_cmd(): bool
    {
        foreach (['c', 'cmd'] as $key) {
            if (
                isset(parent::$data[$key]) &&
                is_string(parent::$data[$key]) &&
                false !== strpos(parent::$data[$key], '/')
            ) {
                parent::$cmd = parent::$data[$key];
                unset(parent::$data[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * Parse cmd data
     */
    private static function parse_cmd(): void
    {
        if ('' === parent::$cmd) return;
        //Extract "cmd" list
        $list = self::get_list(parent::$cmd);
        //Parse "cmd" values
        foreach ($list as $item) {
            //Get module
            $module = self::get_module($item);
            if ('' !== $module) {
                //Module goes here
                //Add module to "parent::$module" if not added
                if (!isset(parent::$module[$module])) parent::$module[$module] = [];
                //Add method to "parent::$module" if not added
                if (!in_array($item, parent::$module[$module], true)) parent::$module[$module][] = $item;
            } else {
                //Method goes here
                //Add to "parent::$method" if not added
                if (!in_array($item, parent::$method, true)) parent::$method[] = $item;
            }
        }
        unset($list, $item, $module);
    }

    /**
     * Execute cmd
     */
    private static function execute_cmd(): void
    {
        //Check main data
        if (
            empty(parent::$module) ||
            (empty(parent::$method) && empty(parent::$data))
        ) {
            if (DEBUG) parent::$result['ERROR'] = 'Missing Data or CMD ERROR!';
            return;
        }

        //Execute queue list
        foreach (parent::$module as $module => $method) {
            //Load Module config file
            $file = realpath(ROOT . '/' . $module . '/config.php');
            if (false !== $file) require $file;
            //Call API
            self::call_api($method);
        }
        unset($module, $method, $file);
    }

    /**
     * Get Module/Method list
     *
     * @param string $lib
     *
     * @return array
     */
    private static function get_list(string $lib): array
    {
        if (false === strpos($lib, '-')) return [$lib];
        //Spilt data when multiple modules/methods exist with "-"
        $list = explode('-', $lib);
        $list = array_filter($list);
        $list = array_unique($list);
        unset($lib);
        return $list;
    }

    /**
     * Get module value
     *
     * @param string $lib
     *
     * @return string
     */
    private static function get_module(string $lib): string
    {
        //Trim "\" and "/"
        $lib = trim($lib, '\\/');
        //Detect module position
        $pos = strpos($lib, '/');
        //Detect module
        $module = false !== $pos ? substr($lib, 0, $pos) : '';
        unset($lib, $pos);
        return $module;
    }

    /**
     * API Caller
     *
     * @param $lib
     */
    private static function call_api(array $lib): void
    {
        foreach ($lib as $class) {
            //Get root class
            $space = '\\' . str_replace('/', '\\', $class);
            //Call methods
            if (class_exists($space)) self::call_class($class, $space);
        }
        unset($lib, $class, $space);
    }

    /**
     * Class Caller
     *
     * @param $class
     * @param $space
     */
    private static function call_class(string $class, string $space): void
    {
        //Check API Safe Key
        if (!isset($space::$key) || !is_array($space::$key)) {
            if (DEBUG) parent::$result[$class] = 'Safe Key ERROR!';
            return;
        }

        //Get API Safe Key list
        $key_list = array_keys($space::$key);

        //Get all methods in class
        $method_list = get_class_methods($space);

        //Get requested api methods
        $key_methods = !empty(parent::$method) ? array_intersect(parent::$method, $key_list, $method_list) : array_intersect($key_list, $method_list);

        //Calling "init" without permission
        if (in_array('init', $method_list, true) && !in_array('init', $key_methods, true)) self::call_method($class, $space, 'init');

        //Run method
        foreach ($key_methods as $method) {
            //Get intersect and difference set of data requirement structure
            $inter = array_intersect(parent::$struct, $space::$key[$method]);
            $diff = array_diff($space::$key[$method], $inter);

            //Skip running method when data structure not match
            if (!empty($diff)) {
                if (DEBUG) parent::$result[$class . '/' . $method] = 'Data Structure ERROR: [' . (implode(', ', $diff)) . '] were missing';
                continue;
            }

            //Call method
            try {
                self::call_method($class, $space, $method);
            } catch (\Throwable $exception) {
                if (DEBUG) parent::$result[$class . '/' . $method] = 'Method Calling Failed: ' . $exception->getMessage();
                unset($exception);
            }
        }
        unset($class, $space, $key_list, $method_list, $key_methods, $method, $inter, $diff);
    }

    /**
     * Method Caller
     *
     * @param string $class
     * @param string $space
     * @param string $method
     */
    private static function call_method(string $class, string $space, string $method): void
    {
        //Get reflection object for class method
        $reflect = new \ReflectionMethod($space, $method);

        //Check visibility and property
        if (!$reflect->isPublic() || !$reflect->isStatic()) return;

        //Calling method
        $result = $space::$method();

        //Build data structure
        parent::build_struct();

        //Save result
        if (isset($result)) parent::$result[$class . '/' . $method] = &$result;
        unset($class, $space, $method, $reflect, $result);
    }
}