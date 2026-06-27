<?php

/**
 * Router library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;

class Router extends Factory
{
    public array $cgi_router_stack = [];
    public array $cli_router_stack = [];
    public array $exe_path_mapping = [];
    public array $module_metadata  = [];

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function parseCli(string $c): array
    {
        $cmd_data = [];

        foreach ($this->cli_router_stack as $router) {
            $cmd_data = $this->process($router, $c);

            if (!empty($cmd_data)) {
                break;
            }
        }

        unset($c, $router);
        return $cmd_data;
    }

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function parseCgi(string $c): array
    {
        $cmd_data = [];

        foreach ($this->cgi_router_stack as $router) {
            $cmd_data = $this->process($router, $c);

            if (!empty($cmd_data)) {
                break;
            }
        }

        unset($c, $router);
        return $cmd_data;
    }

    /**
     * @param string $c
     *
     * @return array
     */
    public function getCliUnit(string $c): array
    {
        $exe = [];

        if (isset($this->exe_path_mapping[$c])) {
            $exe = [$c, $this->exe_path_mapping[$c]];
        }

        unset($c);
        return $exe;
    }

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getCgiUnit(string $c): array
    {
        if ('' === $c) {
            return [];
        }

        $c = strtr($c, '\\', '/');

        if (false === strpos($c, '/', 1)) {
            return [];
        }

        $app = App::new();

        $api_path = strtr($app->api_dir, '\\', '/');
        $api_path = trim($api_path, '/') . '/';
        $cgi_cmd  = $this->getFullCgiCmd($api_path, $c, $app->mode, $app->root_path, $app->is_cli);
        $unit     = $this->getApiFn($cgi_cmd);

        unset($c, $app, $api_path, $cgi_cmd);
        return $unit;
    }

    /**
     * @param string $api_dir
     * @param string $cmd_val
     * @param string $app_mode
     * @param string $root_path
     * @param bool   $is_cli
     *
     * @return string
     */
    public function getFullCgiCmd(string $api_dir, string $cmd_val, string $app_mode, string $root_path, bool $is_cli): string
    {
        $cmd_val = strtr($cmd_val, '\\', '/');

        $is_safe = !$is_cli
            ? str_starts_with($cmd_val, $api_dir)
            : str_starts_with($cmd_val, '/') || str_starts_with($cmd_val, $api_dir);

        $cmd_val = trim($cmd_val, '/');

        if ($is_safe) {
            $cmd = '/' . $cmd_val;
        } elseif (App::MODE_API === $app_mode) {
            $cmd = '/' . $api_dir . $cmd_val;
        } else {
            $module_unit = explode('/', $cmd_val);
            $module_unit = array_filter($module_unit,
                function ($segment)
                {
                    $string = trim($segment, '.');

                    unset($segment);
                    return $string !== '';
                }
            );
            $module_unit = array_values($module_unit);

            if (count($module_unit) < 2) {
                return '';
            }

            $module_path = $root_path . DIRECTORY_SEPARATOR . $api_dir . $module_unit[0];

            if (!is_dir($module_path)) {
                return '';
            }

            $metadata = $this->getModuleMetadata($module_unit[0], $module_path);

            if (empty($metadata)) {
                return '';
            }

            $cmd = '/' . $api_dir . $module_unit[0] . '/' . strstr($metadata['entry'], '.', true) . '/' . $module_unit[1];
            unset($module_unit, $module_path, $metadata);
        }

        unset($api_dir, $cmd_val, $app_mode, $root_path, $is_cli, $is_safe);
        return $cmd;
    }

    /**
     * @param string $cmd
     *
     * @return array
     */
    private function getApiFn(string $cmd): array
    {
        $fn_pos = strrpos($cmd, '/');

        if (false === $fn_pos) {
            return [];
        }

        $fn = [
            strtr(substr($cmd, 0, $fn_pos), '/', '\\'),
            substr($cmd, $fn_pos + 1)
        ];

        unset($cmd, $fn_pos);
        return $fn;
    }

    /**
     * @param string $module_name
     * @param string $module_path
     *
     * @return array
     */
    private function getModuleMetadata(string $module_name, string $module_path): array
    {
        if (!isset($this->module_metadata[$module_name])) {
            $meta_file = $module_path . DIRECTORY_SEPARATOR . 'module.json';

            if (!is_file($meta_file)) {
                return [];
            }

            $metadata = json_decode(file_get_contents($meta_file), true);

            if (is_null($metadata)) {
                return [];
            }

            if (!isset($metadata['name']) || $metadata['name'] !== $module_name) {
                return [];
            }

            if (!isset($metadata['entry']) || !is_file($module_path . DIRECTORY_SEPARATOR . $metadata['entry'])) {
                return [];
            }

            $this->module_metadata[$module_name] = $metadata;
            unset($meta_file, $metadata);
        }

        unset($module_path);
        return $this->module_metadata[$module_name];
    }

    /**
     * @param callable $router
     * @param string   $cmd
     *
     * @return array
     * @throws \ReflectionException
     */
    private function process(callable $router, string $cmd): array
    {
        try {
            $cmd_data = call_user_func($router, $cmd);

            if (is_array($cmd_data)) {
                unset($router, $cmd);
                return $cmd_data;
            }
        } catch (\Throwable $throwable) {
            Error::new()->exceptionHandler($throwable, false, false);
            unset($throwable);
        }

        unset($router, $cmd, $cmd_data);
        return [];
    }
}