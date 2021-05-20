<?php

/**
 * Language Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\App;
use Core\Lib\IOUnit;

/**
 * Class libLang
 * Language file should be located in "root_path/$file_path/$lang_name/LC_MESSAGES/$file_name.mo"
 *
 * @package Ext
 */
class libLang extends Factory
{
    /**
     * Load language file (root_path based)
     *
     * @param string $file_path
     * @param string $file_name
     * @param string $lang_name
     * @param string $root_path
     */
    public function load(string $file_path, string $file_name, string $lang_name = '', string $root_path = ''): void
    {
        if ('' === $lang_name) {
            $lang_name = self::detect();
        }

        putenv('LANG=' . $lang_name);
        setlocale(LC_ALL, $lang_name);
        bindtextdomain($file_name, App::new()->getRootPath($root_path) . DIRECTORY_SEPARATOR . $file_path . DIRECTORY_SEPARATOR);
        textdomain($file_name);

        unset($file_path, $file_name, $lang_name, $root_path);
    }

    /**
     * Detect language
     *
     * @return string
     */
    public function detect(): string
    {
        if ('' !== ($lang = IOUnit::new()->src_input['lang'] ?? '')) {
            return $lang;
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
        } else {
            return 'zh-CN';
        }
    }
}