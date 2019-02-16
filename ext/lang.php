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

use core\system;

class lang
{
    /**
     * Language file directory
     *
     * Related to "ROOT/$dir/"
     * Language file should be located in "ROOT/$dir/self::DIR/$lang/LC_MESSAGES/filename.mo"
     */
    const DIR = 'language';

    /**
     * Load language file
     *
     * @param string $dir
     * @param string $file
     * @param string $lang
     */
    public static function load(string $dir, string $file, string $lang = ''): void
    {
        if ('' === $lang) {
            $lang = self::detect();
        }

        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);

        bindtextdomain($file, ROOT . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR);
        textdomain($file);

        unset($dir, $file, $lang);
    }

    /**
     * Detect language
     *
     * @return string
     */
    private static function detect(): string
    {
        static $lang = '';

        if ('' === $lang) {
            if (isset(system::$data['lang'])) {
                $lang = system::$data['lang'];
            } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = 'zh' === substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ? 'zh-CN' : 'en-US';
            } else {
                $lang = 'en-US';
            }
        }

        return $lang;
    }
}