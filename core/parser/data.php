<?php

/**
 * Data Parser
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

namespace core\parser;

class data
{
    //Base64 data header
    const base64 = 'data:text/argv;base64,';

    /**
     * Encode data in base64 with data header
     *
     * @param string $value
     *
     * @return string
     */
    public static function encode(string $value): string
    {
        return self::base64 . base64_encode($value);
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
        if (0 !== strpos($value, self::base64)) {
            return $value;
        }

        $value = substr($value, strlen(self::base64));
        $value = base64_decode($value, true);

        return $value;
    }

    /**
     * Build argument
     *
     * @param object $reflect
     * @param array  $input
     *
     * @return array
     * @throws \Exception
     */
    public static function build_argv(object $reflect, array $input): array
    {
        //Get method params
        $params = $reflect->getParameters();

        if (empty($params)) {
            return [];
        }

        $data = $diff = [];

        //Process params
        foreach ($params as $param) {
            //Get param name
            $name = $param->getName();

            //Check param data
            if (isset($input[$name])) {
                switch ($param->getType()) {
                    case 'int':
                        $data[] = (int)$input[$name];
                        break;
                    case 'bool':
                        $data[] = (bool)$input[$name];
                        break;
                    case 'float':
                        $data[] = (float)$input[$name];
                        break;
                    case 'array':
                        $data[] = (array)$input[$name];
                        break;
                    case 'string':
                        $data[] = (string)$input[$name];
                        break;
                    case 'object':
                        $data[] = (object)$input[$name];
                        break;
                    default:
                        $data[] = $input[$name];
                        break;
                }
            } else {
                $param->isDefaultValueAvailable() ? $data[] = $param->getDefaultValue() : $diff[] = $name;
            }
        }

        //Report argument missing
        if (!empty($diff)) {
            throw new \Exception('Argument missing [' . (implode(', ', $diff)) . ']');
        }

        unset($reflect, $input, $params, $diff, $param, $name);
        return $data;
    }
}