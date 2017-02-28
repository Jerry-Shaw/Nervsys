<?php

/**
 * Error Controlling Module
 * Version 2.7.0 (Nerve Cell)
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author 彼岸花开 <330931138@qq.com>
 * Author 杨晶 <752050750@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
 * Copyright 2016-2017 彼岸花开
 * Copyright 2016 杨晶
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */
class ctrl_error
{
    //Error message pool
    private static $error_pool = [];

    /**
     * Load an error file from module
     * Storage the array content into $error_storage
     * The content of the error file should be format to JSON
     * The structure should be "integer ERROR CODE":"string ERROR LANGUAGE MAPPED MESSAGE"
     * @param string $module
     * @param string $file
     */
    public static function load(string $module, string $file)
    {
        load_lib('core', 'ctrl_file');
        $json_content = \ctrl_file::get_content(ROOT . '/' . $module . '/_error/' . $file . '.json');
        if ('' !== $json_content) {
            $error_content = json_decode($json_content, true);
            if (isset($error_content)) {
                self::$error_pool = &$error_content;
                if (ERROR_LANG) load_lib('core', 'ctrl_language');
            }
            unset($error_content);
        }
        unset($module, $file, $json_content);
    }

    /**
     * Get a standard error result
     * Language pack needs to be loaded before getting an error message
     * @param int $error_code
     * @return array
     */
    public static function get_error(int $error_code): array
    {
        return array_key_exists($error_code, self::$error_pool)
            ? ['code' => $error_code, 'msg' => ERROR_LANG ? gettext(self::$error_pool[$error_code]) : self::$error_pool[$error_code]]
            : ['code' => $error_code, 'msg' => 'Error code NOT found!'];
    }

    /**
     * Get all the errors
     * Language pack needs to be loaded before getting error messages
     * @return array
     */
    public static function get_all_errors(): array
    {
        $errors = [];
        load_lib('core', 'ctrl_file');
        if (ERROR_LANG) load_lib('core', 'ctrl_language');
        $error_files = \ctrl_file::get_list(ROOT, '*.json', true);//Get all the json formatted error files from all modules
        foreach ($error_files as $file) {
            $json_content = \ctrl_file::get_content($file);
            if ('' !== $json_content) {
                $error_content = json_decode($json_content, true);
                if (isset($error_content)) {
                    if (ERROR_LANG && isset($error_content['Lang'])) {
                        $lang_file = false !== strpos($error_content['Lang'], ', ') ? explode(', ', $error_content['Lang']) : [$error_content['Lang']];
                        foreach ($lang_file as $lang) {
                            \ctrl_language::load($lang, $error_content['Module']);//Load defined language pack
                            $errors[$error_content['CodeRange']] = [];
                            $errors[$error_content['CodeRange']]['Name'] = $error_content['Name'];
                            $errors[$error_content['CodeRange']]['Module'] = '' !== $error_content['Module'] ? $error_content['Module'] : 'core';
                            $errors[$error_content['CodeRange']]['CodeRange'] = $error_content['CodeRange'];
                            foreach ($error_content as $code => $error) {
                                if (is_int($code)) {
                                    $error_text = gettext($error);
                                    $error_content[$code] = $error_text;
                                    $errors[$error_content['CodeRange']]['Errors'][$code] = $error_text;
                                } else continue;
                            }
                        }
                    } else {
                        $errors[$error_content['CodeRange']] = [];
                        $errors[$error_content['CodeRange']]['Name'] = $error_content['Name'];
                        $errors[$error_content['CodeRange']]['Module'] = '' !== $error_content['Module'] ? $error_content['Module'] : 'core';
                        $errors[$error_content['CodeRange']]['CodeRange'] = $error_content['CodeRange'];
                        foreach ($error_content as $code => $error) {
                            if (is_int($code)) $errors[$error_content['CodeRange']]['Errors'][$code] = $error;
                            else continue;
                        }
                    }
                } else continue;
            }
        }
        ksort($errors);
        unset($error_files, $file, $json_content, $error_content, $lang_file, $lang, $code, $error, $error_text);
        return $errors;
    }
}