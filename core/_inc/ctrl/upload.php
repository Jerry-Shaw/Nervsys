<?php

/**
 * Upload Module
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

class upload
{
    //$_FILES['file']
    public static $file = [];

    //BASE64 content
    public static $base64 = '';

    //Allowed extensions
    public static $file_ext = [];

    //File name without extension
    public static $file_name = '';

    //File permissions
    public static $file_mode = 0664;

    //Allowed File size: 20MB by default
    public static $file_size = 20971520;

    //Upload path (Relative to "FILE_PATH")
    public static $save_path = '';

    //Path permissions
    public static $path_mode = 0764;

    //Allowed Extension/Mime-Type
    const mime
        = [
            //docs
            '.xml'  => 'text/xml',
            '.txt'  => 'text/plain',
            '.rtf'  => 'application/rtf',
            '.pdf'  => 'application/pdf',
            '.doc'  => 'application/msword',
            '.xls'  => 'application/vnd.ms-excel',
            '.ppt'  => 'application/vnd.ms-powerpoint',
            '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            //image
            '.gif'  => 'image/gif',
            '.jpg'  => 'image/jpeg',
            '.png'  => 'image/png',
            '.bmp'  => 'image/bmp',

            //video
            '.avi'  => 'video/msvideo',
            '.flv'  => 'video/x-flv',
            '.mov'  => 'video/quicktime',
            '.mp4'  => 'video/mp4',
            '.mpeg' => 'video/mpeg',
            '.wmv'  => 'video/x-ms-wmv',

            //audio
            '.aac'  => 'audio/aac',
            '.m4a'  => 'audio/mp4',
            '.mid'  => 'audio/mid',
            '.mp3'  => 'audio/mpeg',
            '.ogg'  => 'audio/ogg',
            '.wav'  => 'audio/wav',

            //package
            '.7z'   => 'application/x-7z-compressed',
            '.gz'   => 'application/x-gzip',
            '.zip'  => 'application/x-zip-compressed',
            '.rar'  => 'application/octet-stream',
            '.tar'  => 'application/x-tar',

            //misc
            '.apk'  => 'application/vnd.android.package-archive'
        ];

    /**
     * Upload file
     *
     * @return array
     */
    public static function file(): array
    {
        lang::load('core', 'upload');
        error::load('core', 'upload');
        //Empty $_FILES['file']
        if (empty(self::$file)) return error::get(10007);
        //Upload failed when uploading, returned from server
        if (0 !== self::$file['error']) return self::get_error(self::$file['error']);
        //Get file size
        $file_size = self::chk_size(self::$file['size']);
        //File too large
        if (0 === $file_size) return error::get(10004);
        //Check file extension
        $file_ext = self::chk_ext(self::$file['name']);
        //Extension not allowed
        if ('' === $file_ext) return error::get(10003);
        //Get upload path
        $save_path = file::get_path(self::$save_path, self::$path_mode);
        //Upload path Error
        if ('' === $save_path) return error::get(10002);
        //Get file name
        $file_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));
        //Save file
        $url = self::save_file($save_path, $file_name . '.' . $file_ext);
        //Failed to move/copy from tmp file
        if ('' === $url) return error::get(10001);
        //Upload done
        $result              = error::get(10000);
        $result['file_url']  = &$url;
        $result['file_size'] = &$file_size;
        unset($file_size, $file_ext, $save_path, $file_name, $url);
        return $result;
    }

    /**
     * Upload file via base64
     *
     * @return array
     */
    public static function base64(): array
    {
        lang::load('core', 'upload');
        error::load('core', 'upload');
        //Get base64 position
        $base64_pos = strpos(self::$base64, ';base64,');
        //Mime-type not allowed
        if (false === $base64_pos || 0 !== strpos(self::$base64, 'data:')) return error::get(10003);
        //Get Mime-type
        $mime_type = (string)substr(self::$base64, 5, $base64_pos - 5);
        //Get extension from allowed Mime-type list
        $file_ext = (string)array_search($mime_type, self::mime, true);
        //Check file extension
        $file_ext = self::chk_ext($file_ext);
        //Extension not allowed
        if ('' === $file_ext) return error::get(10003);
        //Get binary data
        $binary_data = base64_decode(substr(self::$base64, $base64_pos + 8));
        //Image data error
        if (false === $binary_data) return error::get(10006);
        //Get file size
        $file_size = self::chk_size(strlen($binary_data));
        //File too large
        if (0 === $file_size) return error::get(10004);
        //Get upload path
        $save_path = file::get_path(self::$save_path, self::$path_mode);
        //Upload path Error
        if ('' === $save_path) return error::get(10002);
        //Get file name
        $file_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));
        //Get URL path
        $url_path = $save_path . $file_name . '.' . $file_ext;
        //Get real upload path
        $file_path = FILE_PATH . $url_path;
        //Delete existing file
        if (is_file($file_path)) unlink($file_path);
        //Write to file
        $save_file = (int)file_put_contents($file_path, $binary_data);
        //File write failed
        if (0 === $save_file) return error::get(10001);
        //Set file permissions
        chmod($file_path, self::$file_mode);
        //Upload done
        $result              = error::get(10000);
        $result['file_url']  = &$url_path;
        $result['file_size'] = &$file_size;
        unset($base64_pos, $mime_type, $file_ext, $binary_data, $file_size, $save_path, $file_name, $url_path, $file_path, $save_file);
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
     * @param string $file_name
     *
     * @return string
     */
    private static function chk_ext(string $file_name): string
    {
        $ext = file::get_ext($file_name);
        //Return empty when extension is empty
        if ('' === $ext) return '';
        //Check when defined
        if (!empty(self::$file_ext) && !in_array($ext, self::$file_ext)) return '';
        //Check allowed extension list when not defined
        if (empty(self::$file_ext) && !isset(self::mime[$ext])) return '';
        unset($file_name);
        return $ext;
    }

    /**
     * Save the file from the tmp file
     *
     * @param string $save_path
     * @param string $file_name
     *
     * @return string
     */
    private static function save_file(string $save_path, string $file_name): string
    {
        //Get URL path
        $url_path = $save_path . $file_name;
        //Get real upload path
        $file_path = FILE_PATH . $url_path;
        //Delete existing file
        if (is_file($file_path)) unlink($file_path);
        //Move tmp file
        if (move_uploaded_file(self::$file['tmp_name'], $file_path)) {
            //Set file permissions
            chmod($file_path, self::$file_mode);
            return $url_path;
        }
        //Copy file when move failed
        if (copy(self::$file['tmp_name'], $file_path)) {
            //Set file permissions
            chmod($file_path, self::$file_mode);
            return $url_path;
        }
        //Return empty path when both methods failed
        unset($save_path, $file_name, $url_path, $file_path);
        return '';
    }

    /**
     * Get the error code from the Server
     *
     * @param int $error_code
     *
     * @return array
     */
    private static function get_error(int $error_code): array
    {
        switch ($error_code) {
            case 1:
            case 2:
                return error::get(10004);
            case 3:
                return error::get(10006);
            case 4:
                return error::get(10007);
            case 6:
                return error::get(10005);
            case 7:
                return error::get(10008);
            default:
                return error::get(10001);
        }
    }
}