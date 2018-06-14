<?php
/**
 * Crypt Extension
 *
 * Copyright 2016-2018 SealingP <464485940@qq.com>
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
//file operation class
abstract class fileOperate
{
    /**
     * clean head '/' and foot '/',Win:change '\' to '/',
     * @param string $path
     * @return void
     */
    protected static function clean(string &$path):void
    {
        $path = strtr($path,['\\'=>'/']);

        stripos($path,'/') === 0 && $path = substr($path,1);

        strrpos($path,'/') == strlen($path) - 1 &&
        $path = substr_replace($path,'',strlen($path)-1,strlen($path)-1);
    }
    /**
     * get file mime
     * @param string $file
     * @return string
     */
    protected static function fileMime(string $file):string
    {
        $res = finfo_open(FILEINFO_MIME);
        $file = @finfo_file($res,$file);
        finfo_close($res);
        if (!$file) return '';
        return $file;
    }
}