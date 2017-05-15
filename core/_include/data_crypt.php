<?php

/**
 * Data Encrypt/Decrypt Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author Yara <314850412@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 * Copyright 2017 Yara
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
class data_crypt
{
    //Crypt methods
    const method = ['AES-256-CTR', 'CAMELLIA-256-CFB'];

    /**
     * Encrypt
     *
     * @param string $string
     * @param array $keys
     *
     * @return string
     */
    public static function encode(string $string, array $keys): string
    {
        $string = openssl_encrypt($string, self::method[$keys['alg']], $keys['key'], 0, $keys['iv']);
        if (false === $string) $string = '';
        unset($keys);
        return $string;
    }

    /**
     * Decrypt
     *
     * @param string $string
     * @param array $keys
     *
     * @return string
     */
    public static function decode(string $string, array $keys): string
    {
        $string = openssl_decrypt($string, self::method[$keys['alg']], $keys['key'], 0, $keys['iv']);
        if (false === $string) $string = '';
        unset($keys);
        return $string;
    }

    /**
     * Encrypt with PKey
     *
     * @param string $string
     * @param string $key
     * @param string $type
     *
     * @return string
     */
    public static function encrypt(string $string, string $key, string $type = 'public'): string
    {
        if (in_array($type, ['public', 'private'], true)) {
            $encrypt = 'openssl_' . $type . '_encrypt';
            if ('' === $string || '' === $key || !$encrypt($string, $string, $key)) $string = '';
            if ('' !== $string) $string = base64_encode($string);
            unset($encrypt);
        }
        unset($key, $type);
        return $string;
    }

    /**
     * Decrypt with PKey
     *
     * @param string $string
     * @param string $key
     * @param string $type
     *
     * @return string
     */
    public static function decrypt(string $string, string $key, string $type = 'private'): string
    {
        if (in_array($type, ['public', 'private'], true)) {
            $decrypt = 'openssl_' . $type . '_decrypt';
            if ('' !== $string) $string = base64_decode($string, true);
            if (false === $string || '' === $string || '' === $key || !$decrypt($string, $string, $key)) $string = '';
            unset($decrypt);
        }
        unset($key, $type);
        return $string;
    }

    /**
     * Hash Password
     *
     * @param string $string
     * @param string $codes
     *
     * @return string
     */
    public static function hash_pwd(string $string, string $codes): string
    {
        $noises = str_split($codes, 16);
        $string = 0 === ord(substr($codes, 0, 1)) & 1 ? $noises[0] . ':' . $string . ':' . $noises[2] : $noises[1] . ':' . $string . ':' . $noises[3];
        $string = substr(hash('sha1', $string), 4, 32);
        unset($codes, $noises);
        return $string;
    }

    /**
     * Check Password
     *
     * @param string $input
     * @param string $codes
     * @param string $hash
     *
     * @return bool
     */
    public static function check_pwd(string $input, string $codes, string $hash): bool
    {
        $result = self::hash_pwd($input, $codes) === $hash ? true : false;
        unset($input, $codes, $hash);
        return $result;
    }

    /**
     * Create encrypted content
     *
     * @param string $string
     *
     * @return string
     */
    public static function create_key(string $string): string
    {
        if ('' !== $string) {
            load_lib(CRYPT_PATH, CRYPT_NAME);
            $crypt = '\\' . CRYPT_NAME;
            $key = $crypt::get_key();
            $keys = $crypt::get_keys($key);
            $mixed = $crypt::mixed_key($key);
            $signature = base64_encode($mixed) . '-' . self::encode($string, $keys);
            unset($crypt, $key, $keys, $mixed);
        } else $signature = '';
        unset($string);
        return $signature;
    }

    /**
     * Get decrypted content
     *
     * @param string $signature
     *
     * @return array
     */
    public static function validate_key(string $signature): array
    {
        if (!empty($signature) && false !== strpos($signature, '-')) {
            $codes = explode('-', $signature, 2);
            $mixed = base64_decode($codes[0], true);
            load_lib(CRYPT_PATH, CRYPT_NAME);
            $crypt = '\\' . CRYPT_NAME;
            $key = $crypt::clear_key($mixed);
            $keys = $crypt::get_keys($key);
            $content = self::decode($codes[1], $keys);
            $data = '' !== $content ? json_decode($content, true) : [];
            unset($codes, $mixed, $crypt, $key, $keys, $content);
        } else $data = [];
        unset($signature);
        return $data ?? [];
    }
}
