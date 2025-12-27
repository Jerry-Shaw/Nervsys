<?php

/**
 * Security library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Reflect;

class Security extends Factory
{
    public array $xss_skip_keys = [];

    public array $fn_target_blocked   = [];
    public array $fn_target_invalid   = [];
    public array $fn_argument_invalid = [];

    /**
     * @param string   $class_name
     * @param string   $method_name
     * @param array    $class_args
     * @param int|null $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getApiResource(string $class_name, string $method_name, array $class_args = [], int|null $filter = null): array
    {
        $fn_list  = [];
        $resource = [
            'api'  => current($this->fn_target_invalid),
            'args' => $class_args
        ];

        try {
            $traits  = Reflect::getTraits($class_name);
            $methods = Reflect::getMethods($class_name, $filter);
        } catch (\Throwable $throwable) {
            Error::new()->exceptionHandler($throwable, false, false);
            unset($class_name, $method_name, $class_args, $filter, $fn_list, $traits, $methods, $throwable);
            return $resource;
        }

        /** @var \ReflectionClass $r_class */
        foreach ($traits as $r_class) {
            $r_methods = $r_class->getMethods($filter);
            $fn_list   += array_combine(array_column($r_methods, 'name'), $r_methods);
        }

        $fn_list += array_combine(array_column($methods, 'name'), $methods);

        foreach ([$method_name, '__call', '__callStatic'] as $method) {
            if (!isset($fn_list[$method])) {
                continue;
            }

            if (str_starts_with($fn_list[$method]->class, NS_NAMESPACE) && 'cli' !== PHP_SAPI) {
                $resource['api'] = current($this->fn_target_blocked);
                break;
            }

            $resource['api'] = [!$fn_list[$method]->isStatic() ? parent::getObj($class_name, $class_args) : $class_name, $method];

            if ($method_name !== $method) {
                $fn_params  = Reflect::getCallable($resource['api'])->getParameters();
                $class_args = [$fn_params[0]->name => $method_name, $fn_params[1]->name => $class_args];
            }

            break;
        }

        unset($class_name, $method_name, $class_args, $filter, $fn_list, $traits, $methods, $r_class, $r_methods, $method, $fn_params);
        return $resource;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function antiXss(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->xss_skip_keys, true)) {
                continue;
            }

            if (is_string($value)) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE);
            } elseif (is_array($value)) {
                $value = $this->antiXss($value);
            }
        }

        unset($key, $value);
        return $data;
    }

    /**
     * @param App    $app
     * @param IOData $IOData
     *
     * @return void
     */
    public function fnTargetBlocked(App $app, IOData $IOData): void
    {
        !headers_sent() && http_response_code(403);

        $IOData->src_msg    = ['code' => 403, 'message' => !$app->debug_mode ? 'Permission denied!' : $IOData->src_cmd . ' NOT secure!'];
        $IOData->src_output = null;

        unset($app, $IOData);
    }

    /**
     * @param App    $app
     * @param IOData $IOData
     *
     * @return void
     */
    public function fnTargetInvalid(App $app, IOData $IOData): void
    {
        !headers_sent() && http_response_code(404);

        $IOData->src_msg    = ['code' => 404, 'message' => !$app->debug_mode ? 'Target NOT found!' : $IOData->src_cmd . ' NOT found!'];
        $IOData->src_output = null;

        unset($app, $IOData);
    }

    /**
     * @param App    $app
     * @param IOData $IOData
     * @param string $message
     *
     * @return void
     */
    public function fnArgumentInvalid(App $app, IOData $IOData, string $message): void
    {
        !headers_sent() && http_response_code(500);

        $IOData->src_msg    = ['code' => 500, 'message' => !$app->debug_mode ? 'Server Data Error!' : $message];
        $IOData->src_output = null;

        unset($app, $IOData, $message);
    }
}