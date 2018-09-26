<?php

/**
 * Upload Extension
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

use core\handler\factory;

class upload extends factory
{
    /**
     * Error code
     * 0: UPLOAD_ERR_OK
     * 1: UPLOAD_ERR_INI_SIZE
     * 2: UPLOAD_ERR_FORM_SIZE
     * 3: UPLOAD_ERR_PARTIAL
     * 4: UPLOAD_ERR_NO_FILE
     * 5: UPLOAD_ERR_NO_SAVE_DIR
     * 6: UPLOAD_ERR_NO_TMP_DIR
     * 7: UPLOAD_ERR_CANT_WRITE
     * 8: UPLOAD_ERR_EXTENSION
     * 9: UPLOAD_ERR_DISALLOWED_FILE_SIZE
     * 10: UPLOAD_ERR_DISALLOWED_EXTENSIONS
     *
     * @var int
     */
    private $errno = 0;

    //File
    private $file = [];

    //Permission
    private $perm = 0664;

    //Allowed ext
    private $ext = [];

    //Allowed size
    private $size = 67108864;

    //File save paths
    private $path_url  = 'uploads' . DIRECTORY_SEPARATOR;
    private $path_save = ROOT . 'uploads' . DIRECTORY_SEPARATOR;

    //Default MIME-Type
    const MIME = [
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
     * Receive file
     *
     * @param string $name
     *
     * @return $this
     */
    public function recv(string $name): object
    {
        //Check errno
        if (0 < $this->errno) {
            return $this;
        }

        //Check data
        if (!isset(parent::$data[$name])) {
            $this->errno = 4;
            return $this;
        }

        //Get upload method
        $this->file['method'] = is_array(parent::$data[$name]) ? 'file' : 'base64';

        //Open finfo
        $mime  = false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        //Process file stream
        switch ($this->file['method']) {
            case 'file':
                //Invalid file upload
                if (!isset(parent::$data[$name]['error'])) {
                    $this->errno = 4;
                    return $this;
                }

                //Server side error
                if (0 < parent::$data[$name]['error']) {
                    $this->errno = parent::$data[$name]['error'];
                    return $this;
                }

                //Copy file property
                unset(parent::$data[$name]['error']);
                $this->file['stream'] = parent::$data[$name];

                //Deep detect file type
                $mime = finfo_file($finfo, $this->file['stream']['tmp_name'], FILEINFO_MIME_TYPE);
                break;

            case 'base64':
                //Check base64 file stream
                if (false === $pos = strpos(parent::$data[$name], ';base64,')) {
                    $this->errno = 4;
                    return $this;
                }

                //Process base64 stream
                $this->file['stream'] = [
                    'type' => (string)substr(parent::$data[$name], 5, $pos - 5),
                    'data' => $data = base64_decode(substr(parent::$data[$name], $pos + 8), true),
                    'size' => strlen($data)
                ];

                //Deep detect file type
                $mime = finfo_buffer($finfo, $data, FILEINFO_MIME_TYPE);

                unset($pos, $data);
                break;
        }

        //Close finfo
        finfo_close($finfo);

        //Correct file type
        if (false !== $mime && $this->file['stream']['type'] !== $mime) {
            $this->file['stream']['type'] = &$mime;
        }

        unset($name, $mime, $finfo);
        return $this;
    }

    /**
     * Save file
     *
     * @param string $as
     *
     * @return array
     */
    public function save(string $as = ''): array
    {
        //Check errno
        if (0 < $this->errno) {
            return ['err' => $this->errno];
        }

        //Generate filename
        if ('' === $as) {
            $as = substr(hash('md5', uniqid(mt_rand(), true)), 16);
        }

        //Check file size
        if (0 < $this->size && $this->size < $this->file['stream']['size']) {
            return ['err' => 9];
        }

        //Check file extension
        $ext = (string)array_search($this->file['stream']['type'], self::MIME, true);

        if ('' === $ext && 'file' === $this->file['method']) {
            $ext = file::get_ext($this->file['stream']['name']);
        }

        if ((empty($this->ext) && !isset(self::MIME[$ext])) || (!empty($this->ext && !in_array($ext, $this->ext, true)))) {
            return ['err' => 10];
        }

        //Build save properties
        $file      = $as . '.' . $ext;
        $url_path  = $this->path_url . $file;
        $file_path = $this->path_save . $file;

        unset($as, $ext, $file);

        //Save file stream
        switch ($this->file['method']) {
            case 'file':
                //Delete existing file
                if (is_file($file_path)) {
                    unlink($file_path);
                }

                //Move/Copy tmp file
                if (!move_uploaded_file($this->file['stream']['tmp_name'], $file_path)) {
                    if (copy($this->file['stream']['tmp_name'], $file_path)) {
                        unlink($this->file['stream']['tmp_name']);
                    } else {
                        unlink($this->file['stream']['tmp_name']);
                        return ['err' => 7];
                    }
                }

                break;

            case 'base64':
                //Write file data
                if (file_put_contents($file_path, $this->file['stream']['data']) !== $this->file['stream']['size']) {
                    return ['err' => 7];
                }

                break;

            default:
                return ['err' => 8];
                break;
        }

        //Set permissions
        chmod($file_path, $this->perm);
        unset($file_path);

        return [
            'err'  => 0,
            'url'  => $url_path,
            'size' => $this->file['stream']['size']
        ];
    }
}