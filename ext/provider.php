<?php

/**
 * Provider Extension
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

class provider extends factory
{
    //Class name
    private $class = '';

    //Extend map
    private $extends = [];

    //Method map
    private $methods = [];

    /**
     * Get called class
     */
    public function __construct()
    {
        $this->class = get_class($this);
    }

    /**
     * Free from factory
     */
    public function __destruct()
    {
        parent::free($this->class);
    }

    /**
     * Get object
     *
     * @param string $name
     *
     * @return object
     * @throws \Exception
     */
    public function __get(string $name): object
    {
        if (isset($this->extends[$item = strtolower($name)])) {
            //Call from extend map
            return $this->extends[$item];
        } else {
            throw new \Exception('Call to undefined object ' . $this->class . '->' . $name, E_USER_WARNING);
        }
    }

    /**
     * Call method
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $name, array $arguments)
    {
        if (isset($this->methods[$item = strtolower($name)])) {
            //Call from method map
            $result = empty($arguments)
                ? forward_static_call([$this->extends[$this->methods[$item]], $name])
                : forward_static_call_array([$this->extends[$this->methods[$item]], $name], $arguments);
        } else {
            throw new \Exception('Call to undefined method ' . $this->class . '::' . $name . '()', E_USER_WARNING);
        }

        unset($name, $arguments, $item);
        return $result;
    }

    /**
     * Bind object
     *
     * @param object $object
     * @param string $alias
     *
     * @throws \ReflectionException
     */
    public function bind(object $object, string $alias = ''): void
    {
        //Build alias name
        if ('' === $alias) {
            $alias = get_class($object);

            if (false !== $pos = strrpos($alias, '\\')) {
                $alias = substr($alias, $pos + 1);
            }

            unset($pos);
        }

        //Build extend map
        $this->extends[$alias] = &$object;

        $reflect = parent::reflect_class($object);
        $methods = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

        //Build method map
        foreach ($methods as $method) {
            $this->methods[strtolower($method->getName())] = $alias;
        }

        unset($object, $alias, $reflect, $methods, $method);
    }
}