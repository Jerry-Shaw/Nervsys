<?php

/**
 * Base extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

use core\handler\factory;

class provider extends system
{
    //Class
    private $class = '';

    //Extends
    private $extends = [];

    //Methods
    private $methods = [];

    //Property
    private $property = [];

    /**
     * Get called class
     */
    public function __construct()
    {
        if ('' === $this->class) {
            $this->class = get_class($this);
        }
    }

    /**
     * Set property
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, $value): void
    {
        $this->property[$name] = &$value;
        unset($name, $value);
    }

    /**
     * Get property
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->property[$name];
    }

    /**
     * Call method
     *
     * @param string $name
     * @param array  $argument
     *
     * @return mixed
     * @throws \ErrorException
     */
    public function __call(string $name, array $argument)
    {
        $item = strtolower($name);

        if (isset($this->methods[$item])) {
            //Call from method map
            $result = empty($argument)
                ? forward_static_call([$this->extends[$this->methods[$item]], $name])
                : forward_static_call_array([$this->extends[$this->methods[$item]], $name], $argument);
        } elseif (isset($this->extends[$item])) {
            //Call from extends map
            $result = $this->extends[$item];
        } else {
            //Throw ErrorException
            throw new \ErrorException(
                'Call to undefined method ' . $this->class . '::' . $name . '()',
                E_USER_ERROR, E_USER_ERROR,
                __FILE__, __LINE__
            );
        }

        unset($name, $argument, $item);
        return $result;
    }

    /**
     * Extends new class
     *
     * @param string $class
     * @param array  $argument
     *
     * @throws \ReflectionException
     */
    public function new(string $class, array $argument = []): void
    {
        $this->build_method($name = $this->get_name($class));
        $this->extends[$name['alias']] = factory::new($name['class'], $argument);
        unset($class, $argument, $name);
    }

    /**
     * Extends origin class
     *
     * @param string $class
     * @param array  $argument
     *
     * @throws \ReflectionException
     */
    public function use(string $class, array $argument = []): void
    {
        $this->build_method($name = $this->get_name($class));
        $this->extends[$name['alias']] = factory::use($name['class'], $argument);
        unset($class, $argument, $name);
    }

    /**
     * Get class & alias
     *
     * @param string $class
     *
     * @return array
     */
    private function get_name(string $class): array
    {
        $result = [];

        if (false !== $alias = strripos($class, ' as ')) {
            $result['class'] = substr($class, 0, $alias);
            $result['alias'] = substr($class, $alias + 4);
        } elseif (false !== $alias = strrpos($class, '/')) {
            $result['class'] = &$class;
            $result['alias'] = substr($class, $alias + 1);
        } elseif (false !== $alias = strrpos($class, '\\')) {
            $result['class'] = &$class;
            $result['alias'] = substr($class, $alias + 1);
        } else {
            $result['class'] = $result['alias'] = &$class;
        }

        $result['alias'] = strtolower($result['alias']);

        unset($class, $alias);
        return $result;
    }

    /**
     * Build method
     *
     * @param array $name
     *
     * @throws \ReflectionException
     */
    private function build_method(array $name): void
    {
        $reflect = new \ReflectionClass(parent::build_name($name['class']));
        $methods = $reflect->getMethods(\ReflectionMethod::IS_PUBLIC);

        //Build method map to alias name
        foreach ($methods as $method) {
            $this->methods[strtolower($method->getName())] = $name['alias'];
        }

        unset($name, $reflect, $methods, $method);
    }
}