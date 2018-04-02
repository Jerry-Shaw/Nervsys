<?php

/**
 * Upload Extension
 *
 * Copyright 2016-2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
 * Copyright 2018 jresun <jresun@163.com>
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

class upload
{
    //Allowed extensions
    public static $file_ext = [];

    //File name without extension
    public static $file_name = '';

    //Allowed File size (20MB by default)
    public static $file_size = 20971520;

    //File permission
    public static $file_mode = 0664;

    //Save path (Relative to "$path_root")
    public static $path_save = '';

    //Root path to save uploaded files
    public static $path_root = ROOT;

    //Path permission
    public static $path_mode = 0776;

    //Allowed Extension/Mime-Type
    const mime = [
        //docs
        'xml'  => 'text/xml',
        'txt'  => 'text/plain',
        'rtf'  => 'application/rtf',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'xls'  => 'application/vnd.ms-excel',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        //image
        'gif'  => 'image/gif',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'bmp'  => 'image/bmp',

        //video
        'avi'  => 'video/msvideo',
        'flv'  => 'video/x-flv',
        'mov'  => 'video/quicktime',
        'mp4'  => 'video/mp4',
        'mpeg' => 'video/mpeg',
        'wmv'  => 'video/x-ms-wmv',

        //audio
        'aac'  => 'audio/aac',
        'm4a'  => 'audio/mp4',
        'mid'  => 'audio/mid',
        'mp3'  => 'audio/mpeg',
        'ogg'  => 'audio/ogg',
        'wav'  => 'audio/wav',

        //package
        '7z'   => 'application/x-7z-compressed',
        'gz'   => 'application/x-gzip',
        'zip'  => 'application/x-zip-compressed',
        'rar'  => 'application/octet-stream',
        'tar'  => 'application/x-tar',

        //misc
        'apk'  => 'application/vnd.android.package-archive'
    ];

    /**
     * Upload file
     *
     * @param string $name
     *
     * @return array
     */
    public static function file(string $name): array
    {
        //Load language pack
        lang::$dir = 'upload';
        lang::load('ext', 'upload');

        //Load error pack
        errno::$dir = 'upload';
        errno::load('ext', 'upload');

        //File not exist
        if (!isset($_FILES[$name]) || empty($_FILES[$name])) return errno::get(1007, 1);

        //Copy file
        $file = &$_FILES[$name];

        //Upload failed when uploading, returned from server
        if (0 !== $file['error']) return self::get_error($file['error']);

        //Get file size
        $file_size = self::chk_size($file['size']);

        //File too large
        if (0 === $file_size) return errno::get(1004, 1);

        //Check file extension
        $file_ext = self::chk_ext($file['name']);

        //Extension not allowed
        if ('' === $file_ext) return errno::get(1003, 1);

        //Get upload path
        $save_path = file::get_path(self::$path_save, self::$path_root, self::$path_mode);

        //Upload path Error
        if ('' === $save_path) return errno::get(1002, 1);

        //Get file name
        $save_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));

        //Save file
        $url = self::save_file($file['tmp_name'], $save_path, $save_name . '.' . $file_ext);

        //Failed to move/copy from tmp file
        if ('' === $url) return errno::get(1001, 1);

        //Upload done
        $result = errno::get(1000, 0);
        $result['file_url'] = &$url;
        $result['file_size'] = &$file_size;

        unset($name, $file, $file_size, $file_ext, $save_path, $save_name, $url);
        return $result;
    }

    /**
     * Upload file via base64
     *
     * @param string $base64
     *
     * @return array
     */
    public static function base64(string $base64): array
    {
        //Load language pack
        lang::$dir = 'upload';
        lang::load('ext', 'upload');

        //Load error pack
        errno::$dir = 'upload';
        errno::load('ext', 'upload');

        //Get base64 position
        $data_pos = strpos($base64, ';base64,');

        //Mime-type not allowed
        if (false === $data_pos || 0 !== strpos($base64, 'data:')) return errno::get(1003, 1);

        //Get Mime-type
        $mime_type = (string)substr($base64, 5, $data_pos - 5);

        //Get extension from allowed Mime-type list
        $file_ext = (string)array_search($mime_type, self::mime, true);

        //Check file extension
        $file_ext = self::chk_ext($file_ext);

        //Extension not allowed
        if ('' === $file_ext) return errno::get(1003, 1);

        //Get binary data
        $binary_data = base64_decode(substr($base64, $data_pos + 8));

        //Image data error
        if (false === $binary_data) return errno::get(1006, 1);

        //Get file size
        $file_size = self::chk_size(strlen($binary_data));

        //File too large
        if (0 === $file_size) return errno::get(1004, 1);

        //Get upload path
        $save_path = file::get_path(self::$path_save, self::$path_root, self::$path_mode);

        //Upload path Error
        if ('' === $save_path) return errno::get(1002, 1);

        //Get file name
        $save_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));

        //Get URL path
        $url_path = $save_path . $save_name . '.' . $file_ext;
        //Get real upload path
        $file_path = self::$path_root . $url_path;

        //Delete existing file
        if (is_file($file_path)) unlink($file_path);

        //Write to file
        $save_file = (int)file_put_contents($file_path, $binary_data);

        //File write failed
        if (0 === $save_file) return errno::get(1001, 1);

        //Set file permissions
        chmod($file_path, self::$file_mode);

        //Upload done
        $result = errno::get(1000, 0);
        $result['file_url'] = &$url_path;
        $result['file_size'] = &$file_size;

        unset($base64, $data_pos, $mime_type, $file_ext, $binary_data, $file_size, $save_path, $save_name, $url_path, $file_path, $save_file);
        return $result;
    }

    /**
     * Get and check the file size
     *
     * @param int $file_size
     *
     * @return int
     */
    private static function chk_size(int $file_size): int
    {
        //Return 0 when file size is over limit
        return $file_size <= self::$file_size ? $file_size : 0;
    }

    /**
     * Get and check the file extension
     *
     * @param string $name
     *
     * @return string
     */
    private static function chk_ext(string $name): string
    {
        $ext = !isset(self::mime[$name]) ? file::get_ext($name) : $name;
        //Extension not allowed
        if ((!empty(self::$file_ext) && !in_array($ext, self::$file_ext, true)) || (empty(self::$file_ext) && !isset(self::mime[$ext]))) $ext = '';
        unset($name);
        return $ext;
    }

    /**
     * Save the file from the tmp file
     *
     * @param string $tmp_name
     * @param string $save_path
     * @param string $save_name
     *
     * @return string
     */
    private static function save_file(string $tmp_name, string $save_path, string $save_name): string
    {
        //Get URL path
        $url_path = $save_path . $save_name;
        //Get real upload path
        $file_path = self::$path_root . $url_path;

        //Delete existing file
        if (is_file($file_path)) unlink($file_path);

        //Move tmp file
        if (move_uploaded_file($tmp_name, $file_path)) {
            //Set file permissions
            chmod($file_path, self::$file_mode);
            unset($tmp_name, $save_path, $save_name, $file_path);
            return $url_path;
        }

        //Copy file when move failed
        if (copy($tmp_name, $file_path)) {
            //Set file permissions
            chmod($file_path, self::$file_mode);
            unset($tmp_name, $save_path, $save_name, $file_path);
            return $url_path;
        }

        //Return empty path when both methods failed
        unset($tmp_name, $save_path, $save_name, $url_path, $file_path);
        return '';
    }

    /**
     * Get the error code from the Server
     *
     * @param int $errno
     *
     * @return array
     */
    private static function get_error(int $errno): array
    {
        switch ($errno) {
            case 1:
            case 2:
                return errno::get(1004, 1);
            case 3:
                return errno::get(1006, 1);
            case 4:
                return errno::get(1007, 1);
            case 6:
                return errno::get(1005, 1);
            case 7:
                return errno::get(1008, 1);
            default:
                return errno::get(1001, 1);
        }
    }
}