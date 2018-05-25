<?php

/**
 * Language Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
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

class lang
{
    /**
     * Language file directory
     * Related to "ROOT/module/"
     * Language file should be located in "ROOT/module/$dir/$lang/LC_MESSAGES/filename.mo"
     *
     * @var string
     */
    public static $dir = 'language';

    //Language
    public static $lang = 'en-US';

    /**
     * Load language pack from module
     *
     * @param string $module
     * @param string $file
     */
    public static function load(string $module, string $file): void
    {
        putenv('LANG=' . self::$lang);
        setlocale(LC_ALL, self::$lang);
        bindtextdomain($file, ROOT . '/' . $module . '/' . self::$dir . '/');
        textdomain($file);

        unset($module, $file);
    }

    /**
     * Translate list in language
     *
     * @param array $list
     */
    public static function trans(array &$list): void
    {
        foreach ($list as $key => $item) {
            unset($list[$key]);
            $list[$item] = gettext($item);
        }

        unset($key, $item);
    }
}