<?php

/**
 * Error Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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

namespace core\ctrl;

class error
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
    public static function load(string $module, string $file): void
    {
        $json = (string)file_get_contents(ROOT . '/' . $module . '/_err/' . $file . '.json');
        //Empty file
        if ('' === $json) return;
        $error = json_decode($json, true);
        if (isset($error)) self::$pool = &$error;
        unset($module, $file, $json, $error);
    }

    /**
     * Get a standard error result
     * Language pack needs to be loaded before getting an error message
     *
     * @param int $code
     *
     * @return array
     */
    public static function get(int $code): array
    {
        return array_key_exists($code, self::$pool)
            ? ['code' => $code, 'msg' => ERROR_LANG ? gettext(self::$pool[$code]) : self::$pool[$code]]
            : ['code' => $code, 'msg' => 'Error message NOT found!'];
    }

    /**
     * Get all the errors
     * Language pack needs to be loaded before getting error messages
     *
     * @return array
     */
    public static function get_all(): array
    {
        $errors = [];
        $error_files = file::get_list(ROOT, '/_err/*.json', true);//Get all the json formatted error files from all modules
        foreach ($error_files as $error_file) {
            $json = (string)file_get_contents($error_file);
            //Empty file
            if ('' === $json) continue;
            $error = json_decode($json, true);
            //Incorrect file
            if (!isset($error)) continue;
            if (ERROR_LANG && isset($error['Lang'])) {
                $lang_files = false !== strpos($error['Lang'], ', ') ? explode(', ', $error['Lang']) : [$error['Lang']];
                self::error_text($errors, $error, $lang_files);
            } else {
                self::error_info($errors, $error);
                foreach ($error as $code => $msg) if (is_int($code)) $errors[$error['CodeRange']]['Errors'][$code] = $msg;
            }
        }
        ksort($errors);
        unset($error_files, $error_file, $json, $error, $lang_files, $code, $msg);
        return $errors;
    }

    /**
     * Merge error information
     *
     * @param array $errors
     * @param array $error
     */
    private static function error_info(array &$errors, array $error): void
    {
        $errors[$error['CodeRange']] = [];
        $errors[$error['CodeRange']]['Name'] = $error['Name'];
        $errors[$error['CodeRange']]['Module'] = '' !== $error['Module'] ? $error['Module'] : 'core';
        $errors[$error['CodeRange']]['CodeRange'] = $error['CodeRange'];
        unset($error);
    }

    /**
     * Merge error text with language
     *
     * @param array $errors
     * @param array $error
     * @param array $lang_list
     */
    private static function error_text(array &$errors, array $error, array $lang_list): void
    {
        foreach ($lang_list as $lang) {
            //Load defined language pack
            lang::load($error['Module'], $lang);
            self::error_info($errors, $error);
            foreach ($error as $code => $msg) {
                if (!is_int($code)) continue;
                $error_text = gettext($msg);
                $error[$code] = $error_text;
                $errors[$error['CodeRange']]['Errors'][$code] = $error_text;
            }
        }
        unset($error, $lang_list, $lang, $code, $msg, $error_text);
    }
}