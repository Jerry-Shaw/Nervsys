<?php

/**
 * Upload Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

use core\lib\std\pool;

/**
 * Class upload
 *
 * @package ext
 */
class upload extends factory
{
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

    //Error message
    const ERRNO = [
        UPLOAD_ERR_OK         => 'Upload succeed.',
        UPLOAD_ERR_INI_SIZE   => 'File too large (ini).',
        UPLOAD_ERR_FORM_SIZE  => 'File too large (code).',
        UPLOAD_ERR_PARTIAL    => 'Partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Path NOT exist.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write.',
        UPLOAD_ERR_EXTENSION  => 'Extension blocked.',
    ];

    //Allowed ext
    public $ext = [];

    //Root path
    public $root = ROOT;

    //Permission
    public $perm = 0666;

    //Max size
    public $size = 20971520;

    //File
    private $file = [];

    /**
     * Fetch upload file/base64
     *
     * @param string $name
     * @param string $as
     *
     * @return $this
     */
    public function fetch(string $name, string $as = ''): object
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = \core\lib\stc\factory::build(pool::class);

        //Check file
        if (!isset($unit_pool->data[$name])) {
            $this->file['error'] = UPLOAD_ERR_NO_FILE;
            return $this;
        }

        //Reset file
        $this->file = [];

        //Receive file/base64
        is_array($unit_pool->data[$name]) ? $this->recv_file($unit_pool->data[$name]) : $this->recv_base64($unit_pool->data[$name]);

        //Save file as
        $this->file['save_as'] = '' === $as ? core::get_uuid() : $as;

        unset($name, $as, $unit_pool);
        return $this;
    }

    /**
     * Save file/base64
     *
     * @param string $to
     *
     * @return array
     */
    public function save(string $to = 'upload'): array
    {
        //Check upload error
        if (isset($this->file['error'])) {
            return $this->get_error($this->file['error']);
        }

        //Check file size
        if (0 < $this->size && $this->file['stream']['size'] > $this->size) {
            return $this->get_error(UPLOAD_ERR_FORM_SIZE);
        }

        //Check file extension
        $ext = array_search($this->file['stream']['type'], self::MIME, true);

        if (false === $ext && 'file' === $this->file['method']) {
            $ext = file::get_ext($this->file['stream']['name']);
        }

        if ((empty($this->ext) && !isset(self::MIME[(string)$ext]))
            || (!empty($this->ext) && !in_array($ext, $this->ext, true))
        ) {
            return $this->get_error(UPLOAD_ERR_EXTENSION);
        }

        //Create save path
        if ('' === $save_path = file::get_path($to, $this->root)) {
            return $this->get_error(UPLOAD_ERR_NO_TMP_DIR);
        }

        //Build save properties
        $file_name = $this->file['save_as'] . '.' . $ext;
        $url_path  = ltrim($save_path, '\\/') . $file_name;
        $file_path = rtrim($this->root, '\\/') . DIRECTORY_SEPARATOR . $url_path;

        //Delete existing file
        is_file($file_path) && unlink($file_path);

        //Save file/base64
        if (!$this->{'save_' . $this->file['method']}($file_path)) {
            return $this->get_error(UPLOAD_ERR_CANT_WRITE);
        }

        //Set permissions
        chmod($file_path, $this->perm);

        //Build upload result
        $result = $this->get_error(UPLOAD_ERR_OK);

        //Collect upload data
        $result['url']  = strtr($url_path, '\\', '/');
        $result['name'] = &$file_name;
        $result['size'] = $this->file['stream']['size'];

        unset($to, $ext, $save_path, $file_name, $url_path, $file_path);
        return $result;
    }

    /**
     * Receive file from stream
     *
     * @param array $file
     */
    private function recv_file(array $file): void
    {
        //Server side error
        if (!isset($file['error']) || UPLOAD_ERR_OK !== $file['error']) {
            $this->file['error'] = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return;
        }

        //Copy file property
        unset($file['error']);
        $this->file['method'] = 'file';
        $this->file['stream'] = &$file;

        //Deep detect file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $this->file['stream']['tmp_name'], FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        //Correct file type
        if (false !== $mime && $this->file['stream']['type'] !== $mime) {
            $this->file['stream']['type'] = &$mime;
        }

        unset($file, $finfo, $mime);
    }

    /**
     * Receive file from base64
     *
     * @param string $base64
     */
    private function recv_base64(string $base64): void
    {
        //Invalid base64 upload
        if (false === $pos = strpos($base64, ';base64,')) {
            $this->file['error'] = UPLOAD_ERR_NO_FILE;
            return;
        }

        //Process base64 stream
        $this->file['method'] = 'base64';
        $this->file['stream'] = [
            'type' => substr($base64, 5, $pos - 5),
            'data' => $data = base64_decode(substr($base64, $pos + 8)),
            'size' => strlen($data)
        ];

        //Deep detect base64 type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $data, FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        //Correct file type
        if (false !== $mime && $this->file['stream']['type'] !== $mime) {
            $this->file['stream']['type'] = &$mime;
        }

        unset($base64, $pos, $data, $finfo, $mime);
    }

    /**
     * Save uploaded file
     *
     * @param string $file_path
     *
     * @return bool
     */
    private function save_file(string $file_path): bool
    {
        $save = move_uploaded_file($this->file['stream']['tmp_name'], $file_path)
            || rename($this->file['stream']['tmp_name'], $file_path)
            || copy($this->file['stream']['tmp_name'], $file_path);

        unset($file_path);
        return $save;
    }

    /**
     * Save base64 to file
     *
     * @param string $file_path
     *
     * @return bool
     */
    private function save_base64(string $file_path): bool
    {
        if (!$save = file_put_contents($file_path, $this->file['stream']['data']) === $this->file['stream']['size']) {
            is_file($file_path) && unlink($file_path);
        }

        unset($file_path);
        return $save;
    }

    /**
     * Get error message
     *
     * @param int $errno
     *
     * @return array
     */
    private function get_error(int $errno): array
    {
        return [
            'errno'   => $errno,
            'message' => self::ERRNO[$errno]
        ];
    }
}