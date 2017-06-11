<?php

/**
 * Error Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author 杨晶 <752050750@qq.com>
 *
 * Copyright 2016-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
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
    private static $pool = [];

    /**
     * Load error file from module
     * Storage the array content into $error_storage
     * The content of the error file should be format to JSON
     * The structure should be "integer [ERROR CODE]":"string [LANGUAGE MAPPED MESSAGE]"
     *
     * @param string $module
     * @param string $file
     */
    public static function load(string $module, string $file)
    {
        $json = (string)file_get_contents(ROOT . '/' . $module . '/_error/' . $file . '.json');
        if ('' !== $json) {
            $error = json_decode($json, true);
            if (isset($error)) {
                self::$pool = &$error;
                if (ERROR_LANG) load_lib('core', 'ctrl_language');
            }
            unset($error);
        }
        unset($module, $file, $json);
    }

    /**
     * Get a standard error result
     * Language pack needs to be loaded before getting an error message
     *
     * @param int $code
     *
     * @return array
     */
    public static function get_error(int $code): array
    {
        return array_key_exists($code, self::$pool)
            ? ['code' => $code, 'msg' => ERROR_LANG ? gettext(self::$pool[$code]) : self::$pool[$code]]
            : ['code' => $code, 'msg' => 'Error code NOT found!'];
    }

    /**
     * Get all the errors
     * Language pack needs to be loaded before getting error messages
     *
     * @return array
     */
    public static function get_all_errors(): array
    {
        $errors = [];
        load_lib('core', 'ctrl_file');
        if (ERROR_LANG) load_lib('core', 'ctrl_language');
        $error_files = \ctrl_file::get_list(ROOT, '/_error/*.json', true);//Get all the json formatted error files from all modules
        foreach ($error_files as $error_file) {
            $json = (string)file_get_contents($error_file);
            if ('' !== $json) {
                $error = json_decode($json, true);
                if (isset($error)) {
                    if (ERROR_LANG && isset($error['Lang'])) {
                        $lang_files = false !== strpos($error['Lang'], ', ') ? explode(', ', $error['Lang']) : [$error['Lang']];
                        foreach ($lang_files as $lang_file) {
                            \ctrl_language::load($error['Module'], $lang_file);//Load defined language pack
                            $errors[$error['CodeRange']] = [];
                            $errors[$error['CodeRange']]['Name'] = $error['Name'];
                            $errors[$error['CodeRange']]['Module'] = '' !== $error['Module'] ? $error['Module'] : 'core';
                            $errors[$error['CodeRange']]['CodeRange'] = $error['CodeRange'];
                            foreach ($error as $code => $msg) {
                                if (is_int($code)) {
                                    $error_text = gettext($msg);
                                    $error[$code] = $error_text;
                                    $errors[$error['CodeRange']]['Errors'][$code] = $error_text;
                                } else continue;
                            }
                        }
                    } else {
                        $errors[$error['CodeRange']] = [];
                        $errors[$error['CodeRange']]['Name'] = $error['Name'];
                        $errors[$error['CodeRange']]['Module'] = '' !== $error['Module'] ? $error['Module'] : 'core';
                        $errors[$error['CodeRange']]['CodeRange'] = $error['CodeRange'];
                        foreach ($error as $code => $msg) {
                            if (is_int($code)) $errors[$error['CodeRange']]['Errors'][$code] = $msg;
                            else continue;
                        }
                    }
                } else continue;
            }
        }
        ksort($errors);
        unset($error_files, $error_file, $json, $error, $lang_files, $lang_file, $code, $msg, $error_text);
        return $errors;
    }
}