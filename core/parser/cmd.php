<?php

/**
 * CMD Parser
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

use core\system;

use core\handler\platform;

class cmd extends system
{
    /**
     * Prepare CMD
     */
    public static function prepare(): void
    {
        //Extract CMD
        $cmd = false !== strpos(parent::$cmd, '-') ? explode('-', parent::$cmd) : [parent::$cmd];

        //Prepare CMD
        parent::$cmd_cgi = self::pack_cgi(!empty(parent::$cgi) ? self::prep_cgi($cmd) : $cmd);
        parent::$cmd_cli = parent::$is_CLI ? self::prep_cli($cmd) : [];

        unset($cmd);
    }

    /**
     * Pack CGI CMD
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function pack_cgi(array $cmd): array
    {
        $key  = -1;
        $list = [];

        foreach ($cmd as $item) {
            if (false !== strpos($item, '/') || false !== strpos($item, '\\')) {
                //Move group index key
                ++$key;
            }

            $list[$key][] = $item;
        }

        unset($cmd, $key, $item);
        return $list;
    }

    /**
     * Prepare CGI CMD
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cgi(array $cmd): array
    {
        //Mapping CGI config
        foreach (parent::$cgi as $name => $item) {
            if (!empty($keys = array_keys($cmd, $name, true))) {
                //Full match replace
                foreach ($keys as $key) {
                    $cmd[$key] = $item;
                }

                //Add param
                parent::$param_cgi[$item] = $name;
                unset($keys);
            } else {
                //Partial match replace
                foreach ($cmd as $key => $val) {
                    //Alias name length
                    $len = strlen($name);

                    //Find match position
                    $pos = strpos($val, $name);

                    //Skip middle part replace
                    if (0 !== $pos && strlen($val) !== $pos + $len) {
                        continue;
                    }

                    //Replace CMD
                    $cmd[$key] = substr_replace($val, $item, $pos, $len);

                    //Add param
                    parent::$param_cgi[$cmd[$key]] = $val;
                }

                unset($len, $pos);
            }
        }

        //Build CMD
        $cmd = false !== strpos($val = implode('-', $cmd), '-') ? explode('-', $val) : [$val];

        unset($name, $item, $key, $val);
        return $cmd;
    }

    /**
     * Prepare CLI CMD
     *
     * @param array $cmd
     *
     * @return array
     * @throws \Exception
     */
    private static function prep_cli(array $cmd): array
    {
        //Add PHP command
        if (in_array('PHP', $cmd, true)) {
            parent::$cli['PHP'] = platform::php_path();
        }

        //Check setting
        if (empty(parent::$cli)) {
            return [];
        }

        //Build CMD
        $key   = -1;
        $order = [];

        foreach ($cmd as $item) {
            if (!isset(parent::$cli[$item]) || '' === parent::$cli[$item]) {
                continue;
            }

            $order[$key]['key'] = $item;
            $order[$key]['cmd'] = parent::$cli[$item];

            $order[$key]['ret']  = parent::$param_cli['ret'];
            $order[$key]['time'] = parent::$param_cli['time'];

            if ('' !== parent::$param_cli['pipe']) {
                $order[$key]['pipe'] = parent::$param_cli['pipe'] . PHP_EOL;
            }

            if (!empty(parent::$param_cli['argv'])) {
                $order[$key]['argv'] = ' ' . implode(' ', parent::$param_cli['argv']);
            }
        }

        unset($cmd, $key, $item);
        return $order;
    }
}