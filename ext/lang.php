<?php

/**
 * Language Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace ext;

use core\lib\std\pool;

/**
 * Class lang
 *
 * @package ext
 */
class lang
{
    /**
     * Load language file
     * Language file should be located in "ROOT/$file_path/$lang/LC_MESSAGES/filename.mo"
     *
     * @param string $file_path
     * @param string $file_name
     * @param string $lang_type
     */
    public static function load(string $file_path, string $file_name, string $lang_type = ''): void
    {
        if ('' === $lang_type) {
            $lang_type = self::detect();
        }

        putenv('LANG=' . $lang_type);
        setlocale(LC_ALL, $lang_type);

        $file_path = '/' !== $file_path ? trim($file_path, '\\/') . DIRECTORY_SEPARATOR : '';

        bindtextdomain($file_name, ROOT . DIRECTORY_SEPARATOR . $file_path . DIRECTORY_SEPARATOR);
        textdomain($file_name);

        unset($file_path, $file_name, $lang_type);
    }

    /**
     * Detect language
     *
     * @return string
     */
    public static function detect(): string
    {
        static $lang = '';

        if ('' !== $lang) {
            return $lang;
        }

        //Get request data
        $data = \core\lib\stc\factory::build(pool::class)->data;

        if (isset($data['lang'])) {
            $lang = &$data['lang'];
        } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
        } else {
            $lang = 'en-US';
        }

        unset($data);
        return $lang;
    }
}