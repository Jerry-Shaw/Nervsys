<?php

/**
 * NS System IO controller
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

namespace core\lib\std;

/**
 * Class io
 *
 * @package core\lib\std
 */
class io
{


    /**
     * Read URL CMD
     *
     * @return string
     */
    public function read_url(): string
    {
        //Read from PATH_INFO
        if (isset($_SERVER['PATH_INFO']) && 1 < strlen($_SERVER['PATH_INFO'])) {
            return substr($_SERVER['PATH_INFO'], 1);
        }

        //Read from REQUEST_URI
        if (false === $from = strpos($_SERVER['REQUEST_URI'], '/', 1)) {
            return '';
        }

        if (false === $stop = strpos($_SERVER['REQUEST_URI'], '?')) {
            $stop = strlen($_SERVER['REQUEST_URI']);
        }

        if (1 < $len = $stop - $from) {
            return substr($_SERVER['REQUEST_URI'], $from + 1, $len);
        }

        unset($from, $stop, $len);
        return '';
    }


    /**
     * Read HTTP data
     */
    public function read_http(): array
    {
        return $_FILES + $_POST + $_GET;
    }

    /**
     * Read input data
     */
    public function read_input(): array
    {
        //Read raw data
        if ('' === $input = (string)file_get_contents('php://input')) {
            return [];
        }

        //Decode data in JSON
        if (is_array($data = json_decode($input, true))) {
            unset($input);
            return $data;
        }

        //Decode data in XML
        libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($input);
        $data = false !== $xml ? (array)$xml : [];
        libxml_clear_errors();

        unset($input, $xml);
        return $data;
    }


}