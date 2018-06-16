<?php

/**
 * fileOperate abstract class
 *
 * Copyright 2018 SealingP <464485940@qq.com>
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

namespace ext\lib;

abstract class fileOperate
{
    /**
     * clean head '/' and foot '/'
     *
     * @param string $path
     *
     * @return void
     */
    protected static function clean(string &$path): void
    {
        $path = strtr($path, ['\\' => DIRECTORY_SEPARATOR]);
        $path = trim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * get file mime
     *
     * @param string $file
     *
     * @return string
     */
    protected static function fileMime(string $file): string
    {
        $res  = finfo_open(FILEINFO_MIME);
        $file = finfo_file($res, $file);

        finfo_close($res);

        return (string)$file;
    }
}