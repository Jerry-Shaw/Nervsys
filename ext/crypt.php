<?php

/**
 * Crypt Extension
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

namespace ext;

class crypt
{
    //Keygen class name
    public static $keygen = '\ext\keygen';

    //Crypt methods (need 2)
    public static $method = ['AES-128-XTS', 'AES-256-XTS'];

    //OpenSSL config file path
    public static $ssl_cnf = 'D:/Programs/WebServer/Programs/PHP/extras/ssl/openssl.cnf';

    /**
     * Get RSA Key-Pairs (Public Key & Private Key)
     *
     * @return array
     */
    public static function get_pkey(): array
    {
        $keys = ['public' => '', 'private' => ''];
        $config = ['config' => self::$ssl_cnf];

        $openssl = openssl_pkey_new($config);
        if (false === $openssl) return $keys;

        $public = openssl_pkey_get_details($openssl);

        if (false !== $public) $keys['public'] = &$public['key'];
        if (openssl_pkey_export($openssl, $private, null, $config)) $keys['private'] = &$private;

        openssl_pkey_free($openssl);
        unset($config, $openssl, $public, $private);

        return $keys;
    }

    /**
     * Initial crypt keys
     *
     * @param string $key
     *
     * @return array
     */
    private static function init_keys(string $key): array
    {
        $keygen = self::$keygen;
        $keys = $keygen::get_keys($key);
        $type = self::$method[$keys['alg']];
        $keys = $keygen::fix_keys($keys, openssl_cipher_iv_length($type));
        $keys['alg'] = &$type;
        unset($key, $keygen, $type);
        return $keys;
    }

    /**
     * Encrypt
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function encrypt(string $string, string $key): string
    {
        $keys = self::init_keys($key);
        $string = (string)openssl_encrypt($string, $keys['alg'], $keys['key'], 0, $keys['iv']);
        unset($key, $keys);
        return $string;
    }

    /**
     * Decrypt
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function decrypt(string $string, string $key): string
    {
        $keys = self::init_keys($key);
        $string = (string)openssl_decrypt($string, $keys['alg'], $keys['key'], 0, $keys['iv']);
        unset($key, $keys);
        return $string;
    }

    /**
     * Get the type of RSA Key
     *
     * @param string $key
     *
     * @return string
     */
    private static function get_type(string $key): string
    {
        $start = strlen('-----BEGIN ');
        $end = strpos($key, ' KEY-----', $start);

        $type = false !== $end ? strtolower(substr($key, $start, $end - $start)) : '';

        unset($key, $start, $end);
        return $type;
    }

    /**
     * Encrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function rsa_encrypt(string $string, string $key): string
    {
        $type = '' !== $key ? self::get_type($key) : '';
        if ('' === $type || !in_array($type, ['public', 'private'], true)) return '';

        $encrypt = 'openssl_' . $type . '_encrypt';

        if ('' === $string || !$encrypt($string, $string, $key)) $string = '';
        if ('' !== $string) $string = base64_encode($string);

        unset($key, $type, $encrypt);
        return $string;
    }

    /**
     * Decrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function rsa_decrypt(string $string, string $key): string
    {
        $type = '' !== $key ? self::get_type($key) : '';
        if ('' === $type || !in_array($type, ['public', 'private'], true)) return '';

        $decrypt = 'openssl_' . $type . '_decrypt';

        if ('' !== $string) $string = base64_decode($string, true);
        if (false === $string || '' === $string || !$decrypt($string, $string, $key)) $string = '';

        unset($key, $type, $decrypt);
        return $string;
    }

    /**
     * Hash Password
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function hash_pwd(string $string, string $key): string
    {
        $noises = str_split($key, 16);
        $string = 0 === ord(substr($key, 0, 1)) & 1 ? $noises[0] . ':' . $string . ':' . $noises[2] : $noises[1] . ':' . $string . ':' . $noises[3];
        $string = substr(hash('sha1', $string), 4, 32);
        unset($key, $noises);
        return $string;
    }

    /**
     * Check Password
     *
     * @param string $input
     * @param string $key
     * @param string $hash
     *
     * @return bool
     */
    public static function check_pwd(string $input, string $key, string $hash): bool
    {
        return self::hash_pwd($input, $key) === $hash;
    }

    /**
     * Sign content
     *
     * @param string $string
     * @param string $rsa_key
     *
     * @return string
     */
    public static function sign(string $string, string $rsa_key = ''): string
    {
        if ('' === $string) return '';

        //Prepare key
        $keygen = self::$keygen;
        $key = $keygen::create();
        $mix = $keygen::build($key);

        //Create encrypted signature
        $mix = '' !== $rsa_key ? self::rsa_encrypt($mix, $rsa_key) : (string)base64_encode($mix);
        $signature = '' !== $mix ? $mix . '-' . self::encrypt($string, $key) : '';

        unset($string, $rsa_key, $keygen, $key, $mix);
        return $signature;
    }

    /**
     * Verify content
     *
     * @param string $signature
     * @param string $rsa_key
     *
     * @return string
     */
    public static function verify(string $signature, string $rsa_key = ''): string
    {
        if (empty($signature) || false === strpos($signature, '-')) return '';

        //Decode signature
        $codes = explode('-', $signature, 2);
        $mix = '' !== $rsa_key ? self::rsa_decrypt($codes[0], $rsa_key) : (string)base64_decode($codes[0], true);

        //Key decode failed
        if ('' === $mix) return '';

        //Prepare key
        $keygen = self::$keygen;
        $key = $keygen::rebuild($mix);
        $data = self::decrypt($codes[1], $key);

        unset($signature, $rsa_key, $codes, $mix, $keygen, $key);
        return $data;
    }
}