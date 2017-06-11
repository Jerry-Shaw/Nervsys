<?php

/**
 * File Upload Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2016 Jerry Shaw
 * Copyright 2016 秋水之冰
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
class ctrl_upload
{
    public static $file = [];//$_FILES['file']
    public static $base64 = '';//BASE64 content
    public static $file_ext = [];//Allowed extensions
    public static $file_name = '';//File name without extension
    public static $file_size = 20971520;//Allowed File size: 20MB by default
    public static $save_path = '';//Upload path

    const img_type = [1 => 'gif', 2 => 'jpeg', 3 => 'png', 6 => 'bmp'];//MINE Types of allowed images
    const img_ext = [1 => 'gif', 2 => 'jpg', 3 => 'png', 6 => 'bmp'];//Extensions of allowed images

    /**
     * Upload a file
     *
     * @return array
     */
    public static function upload_file(): array
    {
        load_lib('core', 'ctrl_language');
        load_lib('core', 'ctrl_error');
        load_lib('core', 'ctrl_file');
        \ctrl_language::load('core', 'ctrl_upload');
        \ctrl_error::load('core', 'ctrl_upload');
        if (!empty(self::$file)) {
            if (0 === self::$file['error']) {//Upload success
                $file_size = self::chk_size(self::$file['size']);//Get the file size
                if (0 < $file_size) {
                    $file_ext = self::chk_ext(self::$file['name']);//Check the file extension
                    if ('' !== $file_ext) {
                        $save_path = \ctrl_file::get_path(self::$save_path);//Get the upload path
                        if (':' !== $save_path) {
                            $file_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));//Get the file name
                            $url = self::save_file(self::$file['tmp_name'], $save_path, $file_name, $file_ext);//Save file
                            if ('' !== $url) {//Done
                                $result = \ctrl_error::get_error(10000);//Upload finished
                                $result['file_url'] = &$url;
                                $result['file_size'] = &$file_size;
                            } else $result = \ctrl_error::get_error(10001);//Failed to move/copy from the tmp file
                            unset($file_name, $url);
                        } else $result = \ctrl_error::get_error(10002);//Upload path Error
                        unset($save_path);
                    } else $result = \ctrl_error::get_error(10003);//Extension not allowed
                    unset($file_ext);
                } else $result = \ctrl_error::get_error(10004);//File too large
                unset($file_size);
            } else $result = self::get_error(self::$file['error']);//Upload failed when uploading, returned from server
        } else $result = \ctrl_error::get_error(10007);//Empty $_FILES['file']
        return $result;
    }

    /**
     * Upload an image in base64 format
     *
     * @return array
     */
    public static function upload_base64(): array
    {
        load_lib('core', 'ctrl_language');
        load_lib('core', 'ctrl_error');
        load_lib('core', 'ctrl_file');
        \ctrl_language::load('core', 'ctrl_upload');
        \ctrl_error::load('core', 'ctrl_upload');
        $base64_pos = strpos(self::$base64, 'base64,');//Get the position
        if (0 === strpos(self::$base64, 'data:image/') && false !== $base64_pos) {//Check the canvas data, must be an image
            $data = substr(self::$base64, $base64_pos + 7);//Get the base64 data of the image
            $img_data = base64_decode($data);//Get the binary data of the image
            if (false !== $img_data) {
                $file_size = self::chk_size(strlen($img_data));//Get the file size
                if (0 < $file_size) {
                    $img_info = getimagesizefromstring($img_data);//Get the image information
                    if (array_key_exists($img_info[2], self::img_ext)) {
                        $file_ext = self::img_ext[$img_info[2]];//Get the extension
                        $save_path = \ctrl_file::get_path(self::$save_path);//Get the upload path
                        if (':' !== $save_path) {
                            $file_name = '' !== self::$file_name ? self::$file_name : hash('md5', uniqid(mt_rand(), true));//Get the file name
                            $url_path = $save_path . $file_name . '.' . $file_ext;//Get URL path
                            $file_path = FILE_PATH . $url_path;//Get real upload path
                            if (is_file($file_path)) unlink($file_path);//Delete the file if existing
                            $save_file = (int)file_put_contents($file_path, $img_data);//Write to file
                            if (0 < $save_file) {//Done
                                $result = \ctrl_error::get_error(10000);//Upload finished
                                $result['file_url'] = &$url_path;
                                $result['file_size'] = &$file_size;
                            } else $result = \ctrl_error::get_error(10001);//Failed to write
                            unset($file_name, $url_path, $file_path, $save_file);
                        } else $result = \ctrl_error::get_error(10002);//Upload path Error
                        unset($file_ext, $save_path);
                    } else $result = \ctrl_error::get_error(10003);//Extension not allowed
                    unset($img_info);
                } else $result = \ctrl_error::get_error(10004);//File too large
                unset($file_size);
            } else $result = \ctrl_error::get_error(10006);//Image data error
            unset($data, $img_data);
        } else $result = \ctrl_error::get_error(10003);//Extension not allowed
        unset($base64_pos);
        return $result;
    }

    /**
     * Resize/Crop an image to a giving size
     *
     * @param string $file
     * @param int $width
     * @param int $height
     * @param bool $crop
     */
    public static function image_resize(string $file, int $width, int $height, bool $crop = false)
    {
        $img_info = getimagesize($file);
        if (array_key_exists($img_info[2], self::img_type)) {
            $img_size = $crop ? self::new_img_crop($img_info[0], $img_info[1], $width, $height) : self::new_img_size($img_info[0], $img_info[1], $width, $height);
            if ($img_info[0] !== $img_size['img_w'] || $img_info[1] !== $img_size['img_h']) {
                $type = self::img_type[$img_info[2]];
                $img_create = 'imagecreatefrom' . $type;
                $img_func = 'image' . $type;
                $img_source = $img_create($file);
                $img_thumb = imagecreatetruecolor($img_size['img_w'], $img_size['img_h']);
                switch ($img_info[2]) {
                    case 1://Deal with the transparent color in a GIF
                        $transparent = imagecolorallocate($img_thumb, 0, 0, 0);
                        imagefill($img_thumb, 0, 0, $transparent);
                        imagecolortransparent($img_thumb, $transparent);
                        break;
                    case 3://Deal with the transparent color in a PNG
                        $transparent = imagecolorallocatealpha($img_thumb, 0, 0, 0, 127);
                        imagealphablending($img_thumb, false);
                        imagefill($img_thumb, 0, 0, $transparent);
                        imagesavealpha($img_thumb, true);
                        break;
                }
                imagecopyresampled($img_thumb, $img_source, 0, 0, $img_size['img_x'], $img_size['img_y'], $img_size['img_w'], $img_size['img_h'], $img_size['src_w'], $img_size['src_h']);
                $img_func($img_thumb, $file);
                imagedestroy($img_source);
                imagedestroy($img_thumb);
                unset($type, $img_create, $img_func, $img_source, $img_thumb, $transparent);
            }
            unset($img_size);
        }
        unset($file, $width, $height, $crop, $img_info);
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
        $ext = \ctrl_file::get_ext($file_name);
        if ('' !== $ext && !empty(self::$file_ext) && !in_array($ext, self::$file_ext, true)) $ext = '';//File extension not allowed, set to empty string
        unset($file_name);
        return $ext;
    }

    /**
     * Save the file from the tmp file
     *
     * @param string $file
     * @param string $save_path
     * @param string $file_name
     * @param string $file_ext
     *
     * @return string
     */
    private static function save_file(string $file, string $save_path, string $file_name, string $file_ext): string
    {
        $url_path = $save_path . $file_name . '.' . $file_ext;//Get URL path
        $file_path = FILE_PATH . $url_path;//Get real upload path
        if (is_file($file_path)) unlink($file_path);//Delete the existing file
        $move = move_uploaded_file($file, $file_path);//Move the tmp file to the right path
        if (!$move) {
            $move = copy($file, $file_path);//Failed to move, copy it
            if (!$move) $url_path = '';//Return empty path if failed to copy the file
        }
        unset($file, $save_path, $file_name, $file_ext, $file_path, $move);
        return $url_path;
    }

    /**
     * Get cropped image coordinates according to the giving size
     *
     * @param int $img_width //Original width
     * @param int $img_height //Original height
     * @param int $need_width //Needed width
     * @param int $need_height //Needed height
     *
     * @return array
     */
    private static function new_img_crop(int $img_width, int $img_height, int $need_width, int $need_height): array
    {
        $img_x = $img_y = 0;
        $src_w = $img_width;
        $src_h = $img_height;
        if (0 < $img_width && 0 < $img_height) {
            $ratio_img = $img_width / $img_height;
            $ratio_need = $need_width / $need_height;
            $ratio_diff = round($ratio_img - $ratio_need, 2);
            if (0 < $ratio_diff && $img_height > $need_height) {
                $crop_w = (int)($img_width - $img_height * $ratio_need);
                $img_x = (int)($crop_w / 2);
                $src_w = $img_width - $crop_w;
                unset($crop_w);
            } elseif (0 > $ratio_diff && $img_width > $need_width) {
                $crop_h = (int)($img_height - $img_width / $ratio_need);
                $img_y = (int)($crop_h / 2);
                $src_h = $img_height - $img_y * 2;
                unset($crop_h);
            }
            unset($ratio_img, $ratio_need, $ratio_diff);
        }
        $img_data = ['img_x' => &$img_x, 'img_y' => &$img_y, 'img_w' => &$need_width, 'img_h' => &$need_height, 'src_w' => &$src_w, 'src_h' => &$src_h];
        unset($img_width, $img_height, $need_width, $need_height, $img_x, $img_y, $src_w, $src_h);
        return $img_data;
    }

    /**
     * Get new image size according to the giving size
     *
     * @param int $img_width //Original width
     * @param int $img_height //Original height
     * @param int $need_width //Needed width
     * @param int $need_height //Needed height
     *
     * @return array
     */
    private static function new_img_size(int $img_width, int $img_height, int $need_width, int $need_height): array
    {
        $src_w = $img_width;
        $src_h = $img_height;
        if (0 < $img_width && 0 < $img_height) {
            $ratio_img = $img_width / $img_height;
            $ratio_need = $need_width / $need_height;
            $ratio_diff = round($ratio_img - $ratio_need, 2);
            if (0 < $ratio_diff && $img_width > $need_width) {
                $img_width = &$need_width;
                $img_height = (int)($need_width / $ratio_img);
            } elseif (0 > $ratio_diff && $img_height > $need_height) {
                $img_height = &$need_height;
                $img_width = (int)($need_height * $ratio_img);
            } elseif (0 === $ratio_diff && $img_width > $need_width && $img_height > $need_height) {
                $img_width = &$need_width;
                $img_height = &$need_height;
            }
            unset($ratio_img, $ratio_need, $ratio_diff);
        } else {
            $img_width = &$need_width;
            $img_height = &$need_height;
        }
        $img_data = ['img_x' => 0, 'img_y' => 0, 'img_w' => &$img_width, 'img_h' => &$img_height, 'src_w' => &$src_w, 'src_h' => &$src_h];
        unset($img_width, $img_height, $need_width, $need_height, $src_w, $src_h);
        return $img_data;
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
                $result = \ctrl_error::get_error(10004);
                break;
            case 2:
                $result = \ctrl_error::get_error(10004);
                break;
            case 3:
                $result = \ctrl_error::get_error(10006);
                break;
            case 4:
                $result = \ctrl_error::get_error(10007);
                break;
            case 6:
                $result = \ctrl_error::get_error(10005);
                break;
            case 7:
                $result = \ctrl_error::get_error(10008);
                break;
            default:
                $result = \ctrl_error::get_error(10001);
                break;
        }
        unset($error_code);
        return $result;
    }
}