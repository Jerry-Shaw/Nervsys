<?php

/**
 * NS Factory module
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
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

namespace Core;

use Core\Lib\App;

/**
 * Class Factory
 *
 * @package Core
 */
class Factory
{
    protected static array $obj_list;

    /**
     * Create new object from called class
     *
     * @return $this
     */
    public static function new(): self
    {
        $params = func_get_args();
        $class  = get_called_class();

        if (1 === func_num_args() && is_array($params[0]) && method_exists($class, '__construct')) {
            try {
                //Try to build args for calling class
                $fn_args = Reflect::new()->buildParams($class, '__construct', $params[0]);

                if (empty($fn_args['diff'])) {
                    $params = &$fn_args['param'];
                }

                unset($fn_args);
            } catch (\ReflectionException $exception) {
                App::new()->showDebug($exception, true);
            }
        }

        return self::getObj($class, $params);
    }

    /**
     * Create new object with class name & params
     *
     * @param string $class_name
     * @param array  $class_params
     *
     * @return object
     */
    public static function getObj(string $class_name, array $class_params = []): object
    {
        $key = $class_name;

        if (!empty($class_params)) {
            $key .= json_encode($class_params);
        }

        $hash_key = hash('md5', $key);

        if (!isset(self::$obj_list[$hash_key])) {
            self::$obj_list[$hash_key] = new $class_name(...$class_params);
        }

        unset($class_name, $class_params, $key);
        return self::$obj_list[$hash_key];
    }

    /**
     * Destroy from Factory
     */
    public function destroy(): void
    {
        if (empty($keys = array_keys(self::$obj_list, $this, true))) {
            return;
        }

        foreach ($keys as $key) {
            unset(self::$obj_list[$key]);
        }
    }
}