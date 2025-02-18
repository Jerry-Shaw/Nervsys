<?php

/**
 * Plugin Manager Extension
 *
 * Copyright 2025 秋水之冰 <27206617@qq.com>
 * Copyright 2025 vickywang06 <904428723@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;
use Nervsys\Core\Reflect;

class libPlugin extends Factory
{
    public string $namespace;

    public array $plugin_list = [];

    /**
     * @param string $plugin_namespace
     * @param string $plugin_reg_file
     *
     * @throws \Exception
     */
    public function __construct(string $plugin_namespace, string $plugin_reg_file = 'pluginList')
    {
        $this->namespace  = strtr($plugin_namespace, '/', '\\');
        $plugin_reg_class = $this->namespace . '\\' . $plugin_reg_file;

        if (!class_exists($plugin_reg_class)) {
            throw new \Exception("Plugin :'" . $plugin_reg_class . "' NOT found!");
        }

        $this->plugin_list = parent::getObj($plugin_reg_class)?->items ?? [];

        unset($plugin_namespace, $plugin_reg_file, $plugin_reg_class);
    }

    /**
     * @param array $args
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getPluginList(array $args = []): array
    {
        foreach ($this->plugin_list as $name => $plugin) {
            if (isset($items['preload']) && (is_array($plugin['preload']) || is_string($plugin['preload']))) {
                $preload = $this->callFn($plugin['preload'], $args);

                if (false === $preload) {
                    unset($this->plugin_list[$name]);
                    continue;
                }

                $this->plugin_list[$name]['preload'] = $preload;
            }
        }

        unset($args, $name, $plugin, $preload);
        return $this->plugin_list;
    }

    /**
     * @param string $plug_name
     * @param array  $plug_args
     * @param string $menu_file
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getPluginMenu(string $plug_name, array $plug_args = [], string $menu_file = 'menu'): array
    {
        $plugin_menu = parent::getObj($this->namespace . '\\' . $plug_name . '\\' . $menu_file)?->items ?? [];

        foreach ($plugin_menu as $name => $items) {
            if (isset($items['preload']) && (is_array($items['preload']) || is_string($items['preload']))) {
                $preload = $this->callFn($items['preload'], $plug_args);

                if (false === $preload) {
                    unset($plugin_menu[$name]);
                    continue;
                }

                $plugin_menu[$name]['preload'] = $preload;
            }
        }

        unset($plug_name, $plug_args, $menu_file, $name, $items, $preload);
        return $plugin_menu;
    }

    /**
     * @param array|string $preload
     * @param array        $args
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function callFn(array|string $preload, array $args): mixed
    {
        if (is_array($preload)) {
            $preload[0] = parent::getObj($preload[0], $args);
        }

        $args   = parent::buildArgs(Reflect::getCallable($preload)->getParameters(), $args);
        $result = call_user_func($preload, $args);

        unset($preload, $args);
        return $result;
    }
}
