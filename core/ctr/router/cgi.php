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

use core\ctr\router;

class cgi extends router
{
    //Module list
    private static $module = [];

    //Method list
    private static $method = [];

    /**
     * Run CGI Router
     */
    public static function run(): void
    {
        //Prepare data
        self::prep_data();

        //Parse cmd
        self::parse_cmd();

        //Execute cmd
        self::execute_cmd();
    }

    /**
     * Prepare CGI data
     */
    private static function prep_data(): void
    {
        if ('' !== parent::$cmd) return;

        self::read_http();
        self::read_input();

        self::prep_cmd();
    }

    /**
     * Get data from HTTP Request
     */
    private static function read_http(): void
    {
        $data = !empty($_POST) ? $_POST : (!empty($_GET) ? $_GET : $_REQUEST);

        if (!empty($data)) parent::$data += $data;
        if (!empty($_FILES)) parent::$data += $_FILES;

        unset($data);
    }

    /**
     * Get data from raw input stream
     */
    private static function read_input(): void
    {
        $input = file_get_contents('php://input');
        if (false === $input) return;

        $data = json_decode($input, true);
        if (is_array($data) && !empty($data)) parent::$data += $data;

        unset($input, $data);
    }

    /**
     * Prepare "cmd" data
     */
    private static function prep_cmd(): void
    {
        $val = parent::opt_val(parent::$data, ['c', 'cmd']);
        if ($val['get'] && is_string($val['data']) && false !== strpos($val['data'], '/')) parent::$cmd = &$val['data'];

        unset($val);
    }

    /**
     * Parse "cmd" data
     */
    private static function parse_cmd(): void
    {
        //Extract "cmd" list
        $list = self::get_list(parent::$cmd);

        //Parse "cmd" values
        foreach ($list as $item) {
            //Get module value
            $module = self::get_module($item);

            //Save module & method & function
            if ('' !== $module) {
                if (!isset(self::$module[$module])) self::$module[$module] = [];//Save module
                if (!in_array($item, self::$module[$module], true)) self::$module[$module][] = $item;//Save method
            } elseif (!in_array($item, self::$method, true)) self::$method[] = $item;//Save function
        }

        unset($list, $item, $module);
    }

    /**
     * Execute cmd
     */
    private static function execute_cmd(): void
    {
        //Check module data
        if (empty(self::$module)) {
            debug('CGI', 'Module NOT found!');
            return;
        }

        //Build data structure
        parent::build_struc();

        //Execute queue list
        foreach (self::$module as $module => $method) {
            //Load Module config file
            $file = realpath(ROOT . '/' . $module . '/cfg.php');
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
        //Trim with "/"
        $lib = trim($lib, " /\t\n\r\0\x0B");

        //Detect module
        $pos = strpos($lib, '/');
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
            class_exists($space) ? self::call_class($class, $space) : debug($class, 'Class [' . $space . '] NOT found!');
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
            debug($class, 'Safe Key NOT found!');
            return;
        }

        //Get API Safe Key list & method list
        $key_list = array_keys($space::$key);
        $method_list = get_class_methods($space);

        //Get requested api methods
        $key_methods = !empty(self::$method) ? array_intersect(self::$method, $key_list, $method_list) : array_intersect($key_list, $method_list);

        //Calling "init" method without permission & comparison
        if (in_array('init', $method_list, true) && !in_array('init', $key_methods, true)) {
            try {
                self::call_method($class, $space, 'init');
            } catch (\Throwable $exception) {
                debug($class . '/init', 'Exec Failed! ' . $exception->getMessage());
                unset($exception);
            }
        }

        //Run method
        foreach ($key_methods as $method) {
            //Get intersect and difference set of data requirement structure
            $inter = array_intersect(parent::$struct, $space::$key[$method]);
            $diff = array_diff($space::$key[$method], $inter);

            //Skip running method when data structure not match
            if (!empty($diff)) {
                debug($class . '/' . $method, 'Missing Params [' . (implode(', ', $diff)) . ']!');
                continue;
            }

            //Call method
            try {
                self::call_method($class, $space, $method);
            } catch (\Throwable $exception) {
                debug($class . '/' . $method, 'Exec Failed! ' . $exception->getMessage());
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
     *
     * @throws \ReflectionException
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
        parent::build_struc();

        //Save result
        if (isset($result)) parent::$result[$class . '/' . $method] = &$result;

        unset($class, $space, $method, $reflect, $result);
    }
}