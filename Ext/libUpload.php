<?php

/**
 * Upload Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\IOUnit;

/**
 * Class libUpload
 *
 * @package Ext
 */
class libUpload extends Factory
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

    public IOUnit $io_unit;
    public string $upload_root;

    public array  $ext  = [];
    public int    $perm = 0666;
    public int    $size = 20971520;

    private array $runtime = [];

    /**
     * libUpload constructor.
     *
     * @param string $upload_root
     */
    public function __construct(string $upload_root)
    {
        $this->io_unit     = IOUnit::new();
        $this->upload_root = &$upload_root;
    }

    /**
     * Set allowed extension
     *
     * @param string ...$ext
     *
     * @return $this
     */
    public function setAllowedExt(string ...$ext): self
    {
        $this->ext = &$ext;

        unset($ext);
        return $this;
    }

    /**
     * Set allowed size
     *
     * @param int $size
     *
     * @return $this
     */
    public function setAllowedSize(int $size): self
    {
        $this->size = &$size;

        unset($size);
        return $this;
    }

    /**
     * Set file permission
     *
     * @param int $perm
     *
     * @return $this
     */
    public function setFilePerm(int $perm): self
    {
        $this->perm = &$perm;

        unset($perm);
        return $this;
    }

    /**
     * Fetch upload file/base64
     *
     * @param string $filename
     *
     * @return $this
     */
    public function fetch(string $filename): self
    {
        //Check file
        if (!isset($this->io_unit->src_input[$filename])) {
            $this->runtime['error'] = UPLOAD_ERR_NO_FILE;
            return $this;
        }

        //Reset file
        $this->runtime = [];

        //Receive file/base64
        is_array($this->io_unit->src_input[$filename])
            ? $this->recvFile($this->io_unit->src_input[$filename])
            : $this->recvBase64($this->io_unit->src_input[$filename]);

        unset($filename, $as, $unit_pool);
        return $this;
    }

    /**
     * Save file/base64
     *
     * @param string $to
     * @param string $as
     *
     * @return array
     */
    public function save(string $to = 'upload', string $as = ''): array
    {
        //Check upload error
        if (isset($this->runtime['error'])) {
            return $this->getUploadError($this->runtime['error']);
        }

        //Check file size
        if (0 < $this->size && $this->runtime['size'] > $this->size) {
            return $this->getUploadError(UPLOAD_ERR_FORM_SIZE);
        }

        //Init libFile
        $lib_file = libFile::new();

        //Check file extension
        $ext = array_search($this->runtime['type'], self::MIME, true);

        if (false === $ext && 'saveFile' === $this->runtime['fn']) {
            $ext = $lib_file->getExt($this->runtime['name']);
        }

        if ((empty($this->ext) && !isset(self::MIME[(string)$ext])) || (!empty($this->ext) && !in_array($ext, $this->ext, true))) {
            return $this->getUploadError(UPLOAD_ERR_EXTENSION);
        }

        //Create save path
        if ('' === $save_path = $lib_file->getPath($to, $this->upload_root)) {
            return $this->getUploadError(UPLOAD_ERR_NO_TMP_DIR);
        }

        //Correct filename
        if ('' === $as) {
            $as = 'saveFile' === $this->runtime['fn'] ? md5_file($this->runtime['tmp_name']) : hash('md5', $this->runtime['data']);
        }

        //Build save properties
        $file_name = $as . '.' . $ext;
        $url_path  = ltrim($save_path, '\\/') . $file_name;
        $file_path = rtrim($this->upload_root, '\\/') . DIRECTORY_SEPARATOR . $url_path;

        //Delete existing file
        is_file($file_path) && unlink($file_path);

        //Save file/base64
        if (!$this->{$this->runtime['fn']}($file_path)) {
            return $this->getUploadError(UPLOAD_ERR_CANT_WRITE);
        }

        //Set permissions
        chmod($file_path, $this->perm);

        //Build upload result
        $result = $this->getUploadError(UPLOAD_ERR_OK);

        //Collect upload data
        $result['url']  = strtr($url_path, '\\', '/');
        $result['name'] = &$file_name;
        $result['size'] = $this->runtime['size'];

        unset($to, $ext, $save_path, $file_name, $url_path, $file_path);
        return $result;
    }

    /**
     * Receive file from stream
     *
     * @param array $file
     */
    private function recvFile(array $file): void
    {
        //Server side error
        if (!isset($file['error']) || UPLOAD_ERR_OK !== $file['error']) {
            $this->runtime['error'] = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            return;
        }

        //Copy file property
        unset($file['error']);
        $this->runtime = $file + ['fn' => 'saveFile'];

        //Deep detect file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $this->runtime['tmp_name'], FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        //Correct file type
        if (false !== $mime && $this->runtime['type'] !== $mime) {
            $this->runtime['type'] = &$mime;
        }

        unset($file, $finfo, $mime);
    }

    /**
     * Receive file from base64
     *
     * @param string $base64
     */
    private function recvBase64(string $base64): void
    {
        //Invalid base64 upload
        if (false === $pos = strpos($base64, ';base64,')) {
            $this->runtime['error'] = UPLOAD_ERR_NO_FILE;
            return;
        }

        //Process base64 stream
        $this->runtime = [
            'type' => substr($base64, 5, $pos - 5),
            'data' => $data = base64_decode(substr($base64, $pos + 8)),
            'size' => strlen($data),
            'fn'   => 'saveBase64'
        ];

        //Deep detect base64 type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_buffer($finfo, $data, FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        //Correct file type
        if (false !== $mime && $this->runtime['type'] !== $mime) {
            $this->runtime['type'] = &$mime;
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
    private function saveFile(string $file_path): bool
    {
        $save = move_uploaded_file($this->runtime['tmp_name'], $file_path)
            || rename($this->runtime['tmp_name'], $file_path)
            || copy($this->runtime['tmp_name'], $file_path);

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
    private function saveBase64(string $file_path): bool
    {
        if (!$save = (file_put_contents($file_path, $this->runtime['data']) === $this->runtime['size'])) {
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
    private function getUploadError(int $errno): array
    {
        return [
            'errno'   => &$errno,
            'message' => self::ERRNO[$errno]
        ];
    }
}