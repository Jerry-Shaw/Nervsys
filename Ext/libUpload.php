<?php

/**
 * Upload Extension
 *
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\IOData;

class libUpload extends Factory
{
    const UPLOAD_ERROR = [
        //UPLOAD_ERR_OK
        0 => 'Upload succeeded.',
        //UPLOAD_ERR_INI_SIZE
        1 => 'File too large (0).',
        //UPLOAD_ERR_FORM_SIZE
        2 => 'File too large (1).',
        //UPLOAD_ERR_PARTIAL
        3 => 'Partially uploaded.',
        //UPLOAD_ERR_NO_FILE
        4 => 'No file uploaded.',
        //File type restrict by code
        5 => 'File type NOT allowed.',
        //UPLOAD_ERR_NO_TMP_DIR
        6 => 'Path NOT found.',
        //UPLOAD_ERR_CANT_WRITE
        7 => 'Failed to save file.',
        //UPLOAD_ERR_EXTENSION
        8 => 'Stopped by extension.'
    ];

    public IOData    $IOData;
    public libFileIO $libFileIO;

    public string $upload_path;

    public int $max_size  = 0;
    public int $file_perm = 0666;

    public array $allowed_ext = [];

    public array $upload_runtime = [
        'name'     => '',
        'type'     => '',
        'tmp_name' => '',
        'error'    => UPLOAD_ERR_NO_FILE,
        'size'     => 0
    ];

    public array $upload_result = [
        'errno'  => UPLOAD_ERR_NO_FILE,
        'result' => self::UPLOAD_ERROR[UPLOAD_ERR_NO_FILE],
        'name'   => '',
        'type'   => '',
        'size'   => 0,
        'path'   => '',
        'url'    => ''
    ];

    public array $mime_types = [
        'text/xml'                                                                  => 'xml',
        'text/plain'                                                                => 'txt',
        'application/rtf'                                                           => 'rtf',
        'application/pdf'                                                           => 'pdf',
        'application/msword'                                                        => 'doc',
        'application/vnd.ms-excel'                                                  => 'xls',
        'application/vnd.ms-powerpoint'                                             => 'ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'image/gif'                                                                 => 'gif',
        'image/jpeg'                                                                => 'jpg',
        'image/png'                                                                 => 'png',
        'image/bmp'                                                                 => 'bmp',
        'video/msvideo'                                                             => 'avi',
        'video/quicktime'                                                           => 'mov',
        'video/mp4'                                                                 => 'mp4',
        'video/mpeg'                                                                => 'mpeg',
        'video/x-ms-wmv'                                                            => 'wmv',
        'audio/aac'                                                                 => 'aac',
        'audio/mp4'                                                                 => 'm4a',
        'audio/mid'                                                                 => 'mid',
        'audio/mpeg'                                                                => 'mp3',
        'audio/ogg'                                                                 => 'ogg',
        'audio/wav'                                                                 => 'wav',
        'application/x-7z-compressed'                                               => '7z',
        'application/x-gzip'                                                        => 'gz',
        'application/x-zip-compressed'                                              => 'zip',
        'application/octet-stream'                                                  => 'rar',
        'application/x-tar'                                                         => 'tar'
    ];

    /**
     * @param string $upload_path
     *
     * @throws \ReflectionException
     */
    public function __construct(string $upload_path)
    {
        $this->IOData      = IOData::new();
        $this->libFileIO   = libFileIO::new();
        $this->upload_path = &$upload_path;

        unset($upload_path);
    }

    /**
     * @param string $mime
     * @param string $ext
     *
     * @return $this
     */
    public function addMimeType(string $mime, string $ext): self
    {
        $this->mime_types[$mime] = &$ext;

        unset($mime, $ext);
        return $this;
    }

    /**
     * @param int $file_perm
     *
     * @return $this
     */
    public function setFilePerm(int $file_perm): self
    {
        $this->file_perm = &$file_perm;

        unset($file_perm);
        return $this;
    }

    /**
     * @param string ...$allowed_ext
     *
     * @return $this
     */
    public function setAllowedExt(string ...$allowed_ext): self
    {
        $this->allowed_ext = &$allowed_ext;

        unset($allowed_ext);
        return $this;
    }

    /**
     * @param int $max_size
     *
     * @return $this
     */
    public function setMaxSizeInBytes(int $max_size): self
    {
        $this->max_size = &$max_size;

        unset($max_size);
        return $this;
    }

    /**
     * @param string $io_data_key
     * @param string $save_dir
     * @param string $save_name
     *
     * @return array
     * @throws \ReflectionException
     */
    public function saveFile(string $io_data_key, string $save_dir = '', string $save_name = ''): array
    {
        if (!isset($this->IOData->src_input[$io_data_key])) {
            unset($io_data_key, $save_dir, $save_name);
            return $this->upload_result;
        }

        $upload_runtime = is_string($this->IOData->src_input[$io_data_key])
            ? $this->getBase64File($this->IOData->src_input[$io_data_key])
            : $this->IOData->src_input[$io_data_key];

        if (0 !== $upload_runtime['error']) {
            unset($io_data_key, $save_dir, $save_name);
            return $this->getResult($upload_runtime['error']);
        }

        if (0 < $this->max_size && $upload_runtime['size'] > $this->max_size) {
            unset($io_data_key, $save_dir, $save_name, $upload_runtime);
            return $this->getResult(UPLOAD_ERR_FORM_SIZE);
        }

        if ('' === $save_name) {
            $save_name = &$upload_runtime['name'];
        }

        if (!empty($this->allowed_ext)) {
            if (!in_array($this->libFileIO->getExt($save_name), $this->allowed_ext, true)
                || !in_array($this->mime_types[$upload_runtime['type']] ?? 'tmp', $this->allowed_ext, true)
            ) {
                unset($io_data_key, $save_dir, $save_name, $upload_runtime);
                return $this->getResult(5);
            }
        }

        $file_path = $this->libFileIO->mkPath($save_dir, $this->upload_path) . $save_name;

        file_exists($file_path) && unlink($file_path);

        if (move_uploaded_file($upload_runtime['tmp_name'], $file_path)
            || rename($upload_runtime['tmp_name'], $file_path)
            || copy($upload_runtime['tmp_name'], $file_path)
        ) {
            chmod($file_path, $this->file_perm);

            $upload_result = $this->getResult(
                UPLOAD_ERR_OK,
                [
                    'name' => &$upload_runtime['name'],
                    'type' => &$upload_runtime['type'],
                    'size' => &$upload_runtime['size'],
                    'path' => &$file_path,
                    'url'  => trim(strtr($save_dir, '\\', '/'), '/') . '/' . $save_name
                ]
            );
        } else {
            $upload_result = $this->getResult(UPLOAD_ERR_CANT_WRITE);
        }

        unset($io_data_key, $save_dir, $save_name, $upload_runtime, $file_path);
        return $upload_result;
    }

    /**
     * @param int   $errno
     * @param array $extra_data
     *
     * @return array
     */
    private function getResult(int $errno, array $extra_data = []): array
    {
        $upload_result = $this->upload_result;

        $upload_result['errno']  = $errno;
        $upload_result['result'] = self::UPLOAD_ERROR[$errno];

        $upload_result = array_replace($upload_result, $extra_data);

        unset($errno, $extra_data);
        return $upload_result;
    }

    /**
     * @param string $file_base64
     *
     * @return array
     */
    private function getBase64File(string $file_base64): array
    {
        $base64_pos = strpos($file_base64, ';base64,');

        if (false === $base64_pos) {
            unset($file_base64, $base64_pos);
            return $this->upload_runtime;
        }

        $file_data = base64_decode(substr($file_base64, $base64_pos + 8));
        $file_mime = substr($file_base64, 5, $base64_pos - 5);
        $temp_file = tempnam(sys_get_temp_dir(), 'UPLOAD_');
        $temp_fp   = fopen($temp_file, 'wb');

        fwrite($temp_fp, $file_data);
        fclose($temp_fp);

        register_shutdown_function(
            function (string $temp_file): void
            {
                file_exists($temp_file) && unlink($temp_file);
                unset($temp_file);
            },
            $temp_file
        );

        $upload_runtime = [
            'name'     => basename($temp_file) . '.' . ($this->mime_types[$file_mime] ?? 'tmp'),
            'type'     => &$file_mime,
            'tmp_name' => &$temp_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($file_data)
        ];

        unset($file_base64, $base64_pos, $file_data, $file_mime, $temp_file, $temp_fp);
        return $upload_runtime;
    }
}