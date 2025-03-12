<?php

/**
 * Hook library
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

class Hook extends Factory
{
    public array $hooks   = [];
    public array $target  = [];
    public array $exclude = [];

    /**
     * @param callable $hook_fn
     * @param string   $target_path
     * @param string   ...$exclude_path
     *
     * @return $this
     */
    public function assign(callable $hook_fn, string $target_path, string ...$exclude_path): self
    {
        $hook_hash = $this->createHash($hook_fn);

        $this->hooks[$hook_hash]      = $hook_fn;
        $this->target[$target_path][] = $hook_hash;

        foreach ($exclude_path as $exclude) {
            $this->exclude[$exclude][] = $hook_hash;
        }

        unset($hook_fn, $target_path, $exclude_path, $hook_hash, $exclude);
        return $this;
    }

    /**
     * @param string $full_cmd
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function run(string $full_cmd): bool
    {
        $result = true;

        $targets   = $this->find($full_cmd, $this->target);
        $excludes  = $this->find($full_cmd, $this->exclude);
        $hash_list = array_diff($targets, $excludes);

        foreach ($hash_list as $hash) {
            if (!$this->callFn($this->hooks[$hash])) {
                $result = false;
                break;
            }
        }

        unset($full_cmd, $targets, $excludes, $hash_list, $hash);
        return $result;
    }

    /**
     * @param string $full_cmd
     * @param array  $path_list
     *
     * @return array
     */
    public function find(string $full_cmd, array $path_list): array
    {
        $targets  = [];
        $full_cmd = strtr($full_cmd, '\\', '/');

        ksort($path_list);

        foreach ($path_list as $path => $hash_list) {
            if (str_starts_with($full_cmd, $path)) {
                $targets = array_merge($targets, $hash_list);
            }
        }

        unset($full_cmd, $path_list, $path, $hash_list);
        return $targets;
    }

    /**
     * @param callable $callable
     *
     * @return string
     */
    private function createHash(callable $callable): string
    {
        if (is_array($callable)) {
            $object_id = is_object($callable[0]) ? spl_object_id($callable[0]) : $callable[0];
            $object_id .= '::' . $callable[1];
        } elseif (is_object($callable)) {
            $object_id = spl_object_id($callable);
        } else {
            $object_id = $callable;
        }

        unset($callable);
        return hash('md5', $object_id);
    }

    /**
     * @param callable $hook_fn
     *
     * @return bool
     * @throws \ReflectionException
     */
    private function callFn(callable $hook_fn): bool
    {
        $params = self::buildArgs(Reflect::getCallable($hook_fn)->getParameters(), IOData::new()->src_input);
        $result = call_user_func($hook_fn, ...$params);

        unset($hook_fn, $params);
        return is_null($result) || true === $result;
    }
}