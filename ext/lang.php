<?php

/**
 * Language Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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
    public static function load(string $dir, string $file, string $lang = 'en-US'): void
    {
        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);

        bindtextdomain($file, ROOT . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . self::DIR . DIRECTORY_SEPARATOR);
        textdomain($file);

        unset($dir, $file, $lang);
    }
}