<?php

/**
 * Plugin Manager Extension
 *
 * Copyright 2025 秋水之冰 <27206617@qq.com>
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
        $plugin_reg_class = $plugin_namespace . '\\' . $plugin_reg_file;

        if (!class_exists($plugin_reg_class)) {
            throw new \Exception("Plugin :'" . $plugin_reg_class . "' NOT found!");
        }

        $this->loadPlugins($plugin_reg_class);

        unset($plugin_namespace, $plugin_reg_file, $plugin_reg_class);
    }

    /**
     * @param string $plugin_reg_class
     *
     * @return void
     * @throws \ReflectionException
     */
    public function loadPlugins(string $plugin_reg_class): void
    {
        $this->plugin_list = parent::getObj($plugin_reg_class)->items;

        unset($plugin_reg_class);
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
            if (is_callable($plugin['preload'])) {
                $preload_args   = parent::buildArgs(Reflect::getCallable($plugin['preload'])->getParameters(), $args);
                $preload_result = call_user_func($plugin['preload'], $preload_args);

                if (true !== $preload_result) {
                    unset($this->plugin_list[$name]);
                }
            }
        }

        unset($args, $name, $plugin, $preload_args, $preload_result);
        return $this->plugin_list;
    }

    /**
     * @param string $plug_name
     * @param array  $plug_args
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getPluginMenu(string $plug_name, array $plug_args = []): array
    {
        $plugin_menu = parent::getObj($this->namespace . '\\' . $plug_name . '\\menu')?->items ?? [];

        foreach ($plugin_menu as $name => $items) {
            if (is_callable($items['preload'])) {
                $preload_args   = parent::buildArgs(Reflect::getCallable($items['preload'])->getParameters(), $plug_args);
                $preload_result = call_user_func($items['preload'], $preload_args);

                if (true !== $preload_result) {
                    unset($plugin_menu[$name]);
                }
            }
        }

        unset($plug_name, $plug_args, $name, $items, $preload_args, $preload_result);
        return $plugin_menu;
    }
}