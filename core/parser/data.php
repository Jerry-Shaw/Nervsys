<?php

/**
 * Data Parser
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\parser;

use core\handler\factory;

class data
{
    //Base64 data header
    const BASE64 = 'data:text/argv;base64,';

    /**
     * Encode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public static function encode(string $value): string
    {
        return self::BASE64 . base64_encode($value);
    }

    /**
     * Decode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public static function decode(string $value): string
    {
        if (0 === strpos($value, self::BASE64)) {
            $value = substr($value, strlen(self::BASE64));
            $value = base64_decode($value);
        }

        return $value;
    }

    /**
     * Build XML
     *
     * @param array $data
     * @param bool  $root
     * @param bool  $pretty
     *
     * @return string
     */
    public static function build_xml(array $data, bool $root = true, bool $pretty = false): string
    {
        $xml = $end = '';

        if ($root && 1 < count($data)) {
            $xml .= '<xml>';
            $end = '</xml>';

            if ($pretty) {
                $xml .= PHP_EOL;
            }
        }

        foreach ($data as $key => $item) {
            if (is_numeric($key)) {
                $key = 'xml_' . $key;
            }

            $xml .= '<' . $key . '>';

            $xml .= is_array($item)
                ? self::build_xml($item, false, $pretty)
                : (!is_numeric($item) ? '<![CDATA[' . $item . ']]>' : $item);

            $xml .= '</' . $key . '>';

            if ($pretty) {
                $xml .= PHP_EOL;
            }
        }

        if ($root) {
            $xml .= $end;
        }

        unset($data, $root, $pretty, $end, $key, $item);
        return $xml;
    }

    /**
     * Build argument
     *
     * @param \ReflectionMethod $reflect
     * @param array             $input
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function build_argv(\ReflectionMethod $reflect, array $input): array
    {
        //Get method params
        if (empty($params = $reflect->getParameters())) {
            return [];
        }

        $data = $diff = [];

        //Process param data
        foreach ($params as $param) {
            //Get param name
            $param_name = $param->getName();

            //Check non-exist param
            if (!isset($input[$param_name])) {
                if ($param->isDefaultValueAvailable()) {
                    //Param has default value
                    $data[] = $param->getDefaultValue();
                    continue;
                } else {
                    //Param has NO default value
                    if (!is_object($param_class = $param->getClass())) {
                        $diff[] = $param_name;
                        continue;
                    }

                    //Try simple injection
                    $data[] = factory::obtain($param_class->getName());
                    continue;
                }
            }

            //Process param with type
            switch (is_object($param_type = $param->getType()) ? $param_type->getName() : 'undefined') {
                case 'int':
                    is_numeric($input[$param_name]) ? $data[] = (int)$input[$param_name] : $diff[] = $param_name;
                    break;
                case 'bool':
                    is_bool($input[$param_name]) ? $data[] = (bool)$input[$param_name] : $diff[] = $param_name;
                    break;
                case 'float':
                    is_numeric($input[$param_name]) ? $data[] = (float)$input[$param_name] : $diff[] = $param_name;
                    break;
                case 'array':
                    is_array($input[$param_name]) || is_object($input[$param_name]) ? $data[] = (array)$input[$param_name] : $diff[] = $param_name;
                    break;
                case 'string':
                    is_string($input[$param_name]) || is_numeric($input[$param_name]) ? $data[] = trim((string)$input[$param_name]) : $diff[] = $param_name;
                    break;
                case 'object':
                    is_object($input[$param_name]) || is_array($input[$param_name]) ? $data[] = (object)$input[$param_name] : $diff[] = $param_name;
                    break;
                default:
                    $data[] = $input[$param_name];
                    break;
            }
        }

        //Report argument missing
        if (!empty($diff)) {
            throw new \Exception(
                $reflect->getDeclaringClass()->getName() . '::' . $reflect->getName()
                . ': Argument mismatch [' . (implode(', ', $diff)) . ']'
            );
        }

        unset($reflect, $input, $params, $diff, $param, $param_name, $param_class, $param_type);
        return $data;
    }
}