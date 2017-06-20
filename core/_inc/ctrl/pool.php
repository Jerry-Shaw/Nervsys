<?php

/**
 * Data Pool Module
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

namespace core\ctrl;

//todo this script needs fully rebuild for new structure

class pool
{
    //CLI data
    public static $cli = [];

    //Data package
    public static $data = [];

    //Result data pool
    public static $pool = [];

    //Result data format (json/raw)
    public static $format = 'json';

    //Module list
    private static $module = [];

    //Method list
    private static $method = [];

    //Keymap list
    private static $keymap = [];

    //Data Structure
    private static $struct = [];

    /**
     * Initial Data Module
     * Only static methods are supported
     */
    public static function start()
    {
        //Get date from HTTP Request or CLI variables
        $data = 'cli' !== PHP_SAPI ? (ENABLE_GET ? $_REQUEST : $_POST) : self::$cli;
        //Set result data format according to the request
        if (isset($data['format']) && in_array($data['format'], ['json', 'raw'], true)) self::$format = &$data['format'];
        //Parse "cmd" data from HTTP Request
        if (isset($data['cmd']) && is_string($data['cmd']) && false !== strpos($data['cmd'], '/')) self::parse_cmd($data['cmd']);
        //Parse "map" data from HTTP Request
        if (isset($data['map']) && is_string($data['map']) && false !== strpos($data['map'], '/') && false !== strpos($data['map'], ':')) self::parse_map($data['map']);
        //Unset "format" & "cmd" & "map" from request data package
        unset($data['format'], $data['cmd'], $data['map']);
        //Store data package to data pool
        self::$data = &$data;
        //Merge "$_FILES" into data pool if exists
        if (!empty($_FILES)) self::$data = array_merge(self::$data, $_FILES);
        //Continue running if requested data is ready
        if (!empty(self::$module) && (!empty(self::$method) || !empty(self::$data))) {
            //Build data structure
            self::$struct = array_keys(self::$data);
            //Parse Module & Method list
            foreach (self::$module as $module => $libraries) {
                //Load Module CFG file for the first time
                load_lib($module, 'cfg');
                //Load Libraries
                foreach ($libraries as $library) {
                    //Load library file
                    $class = load_lib($module, $library);
                    //Check the load status
                    if ('' !== $class) {
                        //Get method list from the class
                        $method_list = get_class_methods($class);
                        //Security Checking
                        if (SECURE_API) {
                            //Checking API Safe Zone
                            $api_list = isset($class::$api) && is_array($class::$api) ? array_keys($class::$api) : [];
                            //Get api methods according to requested methods or all methods will be stored in the intersect list if no method is provided
                            $method_api = !empty(self::$method) ? array_intersect(self::$method, $api_list, $method_list) : array_intersect($api_list, $method_list);
                            //Calling "init" method at the first place if exists without API permission and data structure comparison
                            if (in_array('init', $method_list, true) && !in_array('init', $method_api, true)) self::call_method($module, $class, 'init');
                            //Go through every method in the api list with API Safe Zone checking
                            foreach ($method_api as $method) {
                                //Get the intersect list of the data requirement structure
                                $intersect = array_intersect(self::$struct, $class::$api[$method]);
                                //Get the different list of the data requirement structure
                                $difference = array_diff($class::$api[$method], $intersect);
                                //Calling the api method if the data structure is matched
                                if (empty($difference)) self::call_method($module, $class, $method);
                            }
                        } else if (!empty(self::$method)) {
                            //Requested methods is needed when API Safe Zone checking is turned off
                            $method_api = array_intersect(self::$method, $method_list);
                            //Calling "init" method at the first place if exists without API permission and data structure comparison
                            if (in_array('init', $method_list, true) && !in_array('init', $method_api, true)) self::call_method($module, $class, 'init');
                            //Calling the api method without API Safe Zone checking
                            foreach ($method_api as $method) self::call_method($module, $class, $method);
                        }
                    } else continue;
                }
            }
            unset($module, $libraries, $library, $class, $api_list, $method_list, $method_api, $method, $intersect, $difference);
        }
        unset($data);
    }

    /**
     * "cmd" value parser
     *
     * @param string $data
     *
     * "cmd" value should at least contain "/", or with "," for specific methods calling
     * Format should be some string like but no need to be exact as, examples as follows:
     * One module calling: "module_1/library_1"
     * One module calling with one or more methods: "module_1/library_1,method_1" or "module_1/library_1,method_1,method_2,..."
     * Multiple modules calling: "module_1/library_1,module_2/library_2,..."
     * Multiple modules calling with methods: "module_1/library_1,module_2/library_2,...,method_1,method_2,method_3,method_4,..."
     * Modules with namespace: "module_1/\namespace\library_1" or "module_1/\namespace\library_1,method_1,method_2,..."
     * Mixed modules: "module_1/\namespace\library_1,module_2/library_2"
     * Mixed modules with methods: "module_1/\namespace\library_1,module_2/library_2,...,method_1,method_2,method_3,method_4,..."
     * All mixed: "module_1/\namespace\library_1,method_1,method_2,module_2/library_2,method_3,method_4,..."
     * Notice: The key to calling a method in a module is the structure of data. All/Specific methods will only run with the matched data structure.
     */
    private static function parse_cmd(string $data)
    {
        //Extract "cmd" values
        if (false !== strpos($data, ',')) {
            //Spilt "cmd" value if multiple modules/methods exist with ","
            $cmd = explode(',', $data);
            $cmd = array_filter($cmd);
            $cmd = array_unique($cmd);
        } else $cmd = [$data];
        //Parse "cmd" values
        foreach ($cmd as $item) {
            //Get the position of module path
            $position = strpos($item, '/');
            if (false !== $position) {
                //Module goes here
                //Get module and library names
                $module = substr($item, 0, $position);
                $library = substr($item, $position + 1);
                //Make sure the parsed results are available
                if (false !== $module && false !== $library) {
                    //Add module to "self::$module" if not added
                    if (!isset(self::$module[$module])) self::$module[$module] = [];
                    //Add library to "self::$module" if not added
                    if (!in_array($library, self::$module[$module], true)) self::$module[$module][] = $library;
                } else continue;
            } else {
                //Method goes here
                //Add to "self::$method" if not added
                if (!in_array($item, self::$method, true)) self::$method[] = $item;
            }
        }
        unset($data, $cmd, $item, $position, $module, $library);
    }

    /**
     * "map" value parser
     *
     * @param string $data
     *
     * "map" value should at least contain "/" and ":", or with "," for multiple result mapping
     * Format should be some string like but no need to be exact as, examples as follows:
     * Full result mapping: "module_1/library_1/method_1:key_1,..."
     * Deep structure mapping: "module_1/library_1/method_1/key_A/key_B/key_C:key_1,..."
     * Mixed mapping: "module_1/library_1/method_1:key_1,module_1/library_1/method_1/key_A/key_B/key_C:key_1,..."
     * Module with namespace: "module_1/\namespace\library_1/method_1:key_1,module_2/\namespace\library_2/method_2/result_key:key_2,..."
     * Notice: API running follows the input sequence, the former content will be replaced if the coming one has the same key.
     */
    private static function parse_map(string $data)
    {
        //Extract "map" values
        if (false !== strpos($data, ',')) {
            //Spilt "map" value if multiple modules/methods exist with ","
            $map = explode(',', $data);
            $map = array_filter($map);
            $map = array_unique($map);
        } else $map = [$data];
        //Deeply parse the map values
        foreach ($map as $value) {
            //Every map value should contain both "/" and ":"
            $position = strpos($value, ':');
            if (false !== strpos($value, '/') && false !== $position) {
                //Extract and get map "from" and "to"
                $map_from = substr($value, 0, $position);
                $map_to = substr($value, $position + 1);
                //Deeply parse map "from"
                if (false !== strpos($map_from, '/')) {
                    $keys = explode('/', $map_from);
                    //Map keys should always be greater than 3
                    if (3 <= count($keys)) {
                        $key_from = $keys[0] . '/' . $keys[1] . '/' . $keys[2];
                        unset($keys[0], $keys[1], $keys[2]);
                        $data_from = [];
                        foreach ($keys as $key) $data_from[] = $key;
                        //Save to keymap List
                        self::$keymap[$key_from] = ['from' => $data_from, 'to' => $map_to];
                    } else continue;
                } else continue;
            } else continue;
        }
        unset($data, $map, $value, $position, $map_from, $map_to, $keys, $key_from, $data_from, $key);
    }

    /**
     * Call method and store the result for using/mapping
     *
     * @param string $module
     * @param string $class
     * @param string $method
     */
    private static function call_method(string $module, string $class, string $method)
    {
        //Get a reflection object for the class method
        $reflect = new \ReflectionMethod($class, $method);
        //Check the visibility and property of the method
        if ($reflect->isPublic() && $reflect->isStatic()) {
            //Try to call the method and catch the Exceptions or Errors
            try {
                //Calling method
                $result = $class::$method();
                //Merge result
                if (isset($result)) {
                    //Save result to the result data pool
                    self::$pool[$module . '/' . $class . '/' . $method] = $result;
                    //Check keymap request with result data
                    if (isset(self::$keymap[$module . '/' . $class . '/' . $method])) {
                        //Processing array result to get the final data
                        if (!empty(self::$keymap[$module . '/' . $class . '/' . $method]['from']) && is_array($result)) {
                            //Check every key in keymap from request
                            foreach (self::$keymap[$module . '/' . $class . '/' . $method]['from'] as $key) {
                                //Check key's existence
                                if (isset($result[$key])) {
                                    //Switch result data to where we find
                                    unset($tmp);
                                    $tmp = $result[$key];
                                    unset($result);
                                    $result = $tmp;
                                } else {
                                    //Unset result data if requested key does not exist
                                    unset($result);
                                    break;
                                }
                            }
                        }
                        //Map processed result data to request data pool if isset
                        if (isset($result)) {
                            //Caution: The data with the same key in data pool will be overwritten if exists
                            self::$data[self::$keymap[$module . '/' . $class . '/' . $method]['to']] = $result;
                            //Rebuild data structure
                            self::$struct = array_keys(self::$data);
                        }
                    }
                }
            } catch (\Throwable $exception) {
                //Save the Exception or Error Message to the result data pool instead
                self::$pool[$module . '/' . $class . '/' . $method] = $exception->getMessage();
            }
        }
    }
}