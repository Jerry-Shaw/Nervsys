<?php

/**
 * Captcha Image Extension
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

/**
 * Class libCaptcha
 *
 * @package Ext
 */
class libCaptcha extends Factory
{
    //Predefined types
    const TYPE_MIX  = 'mix';
    const TYPE_NUM  = 'num';
    const TYPE_WORD = 'word';
    const TYPE_PLUS = 'plus';
    const TYPE_CALC = 'calc';

    //Key prefix in Redis
    const KEY_PREFIX = 'CAPTCHA:';

    //Supported types
    const TYPES = [
        self::TYPE_MIX,
        self::TYPE_NUM,
        self::TYPE_WORD,
        self::TYPE_PLUS,
        self::TYPE_CALC,
    ];

    /** @var \ext\libCrypt $lib_crypt */
    public object $lib_crypt;

    /** @var \Redis $redis */
    public \Redis $redis;

    //Code output types
    protected array $types = [];

    //Image size (in pixels)
    protected int $width  = 240;
    protected int $height = 80;

    //Length (only works for "num" & "word")
    protected int $length = 6;

    //Font filename (stored in "/ext/fonts/")
    protected string $font_file = 'font.ttf';

    /**
     * Bind to Crypt object
     *
     * @param object $lib_crypt
     *
     * @return $this
     */
    public function bindCrypt(object $lib_crypt): self
    {
        $this->lib_crypt = &$lib_crypt;

        unset($lib_crypt);
        return $this;
    }

    /**
     * Bind to Redis connection
     *
     * @param \Redis $redis
     *
     * @return $this
     */
    public function bindRedis(\Redis $redis): self
    {
        $this->redis = &$redis;

        unset($redis);
        return $this;
    }

    /**
     * Set img size
     *
     * @param int $width
     * @param int $height
     *
     * @return $this
     */
    public function setSize(int $width = 240, int $height = 80): self
    {
        $this->width  = &$width;
        $this->height = &$height;

        unset($width, $height);
        return $this;
    }

    /**
     * Set font file
     *
     * @param string $font_file
     *
     * @return $this
     */
    public function setFont(string $font_file): self
    {
        $this->font_file = &$font_file;

        unset($font_file);
        return $this;
    }

    /**
     * Set length (only works for "num" & "word")
     *
     * @param string $length
     *
     * @return $this
     */
    public function setLength(string $length): self
    {
        $this->length = &$length;

        unset($length);
        return $this;
    }

    /**
     * Set code types
     *
     * @param string ...$type
     *
     * @return $this
     */
    public function setTypes(string ...$type): self
    {
        $this->types = &$type;

        unset($type);
        return $this;
    }

    /**
     * Get Code
     *
     * @param int $life
     *
     * @return array
     * @throws \Exception
     */
    public function get(int $life = 60): array
    {
        //Validate code types
        $types = !empty($this->types) ? array_intersect($this->types, self::TYPES) : self::TYPES;

        //Generate Auth Code
        $codes = $this->{'build' . ucfirst($types[mt_rand(0, count($types) - 1)])}();

        //Generate encrypt code
        $codes['hash'] = $this->genHash($codes['hash'], $life);

        $font_height = (int)($this->height / 1.6);
        $font_width  = (int)($this->width / count($codes['char']));
        $font_size   = $font_width < $font_height ? $font_width : $font_height;

        $top_padding  = (int)($this->height - ($this->height - $font_size) / 1.8);
        $left_padding = (int)(($this->width - $font_size * count($codes['char'])) / 2);

        //Create image
        $image = imagecreate($this->width, $this->height);

        //Fill image in white
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        //Generator colors
        $colors = [];
        for ($i = 0; $i < 255; ++$i) {
            $color = imagecolorallocate($image, mt_rand(0, 180), mt_rand(0, 180), mt_rand(0, 180));

            if (false !== $color) {
                $colors[] = $color;
            }
        }

        $color_index = count($colors) - 1;

        //Draw text
        foreach ($codes['char'] as $text) {
            imagettftext(
                $image,
                (int)($font_size * mt_rand(88, 112) / 100),
                mt_rand(-18, 18),
                $left_padding,
                $top_padding,
                $colors[mt_rand(0, $color_index)],
                $this->font_file,
                $text
            );

            $left_padding += $font_size * strlen($text);
        }

        unset($codes['char'], $text);

        //Add arc noise
        $noise_count = ceil($this->height / 8);
        for ($i = 0; $i < $noise_count; ++$i) {
            imagearc(
                $image,
                mt_rand(0, $this->width),
                mt_rand(0, $this->height),
                mt_rand(0, $this->width),
                mt_rand(0, $this->height),
                mt_rand(0, 360),
                mt_rand(0, 360),
                $colors[mt_rand(0, $color_index)]
            );
        }

        //Add point noise
        $noise_count = $this->height * 16;
        for ($i = 0; $i < $noise_count; ++$i) {
            imagesetpixel(
                $image,
                mt_rand(0, $this->width),
                mt_rand(0, $this->height),
                $colors[mt_rand(0, $color_index)]
            );
        }

        unset($colors, $color_index, $noise_count, $i);

        //Start output buffer
        ob_clean();
        ob_start();

        //Output image
        imagejpeg($image, null, 25);
        imagedestroy($image);

        //Capture output
        $codes['image'] = 'data:image/jpeg;base64,' . base64_encode(ob_get_contents());

        //Clean output buffer
        ob_clean();
        ob_end_clean();

        unset($types, $font_height, $font_width, $font_size, $top_padding, $left_padding, $image);
        return $codes;
    }

    /**
     * Check code hash with user input
     *
     * @param string $hash
     * @param string $input
     *
     * @return bool
     * @throws \Exception
     */
    public function check(string $hash, string $input): bool
    {
        $result = $this->getCode($hash) === strtoupper($input);

        unset($hash, $input);
        return $result;
    }

    /**
     * Generate code hash
     *
     * @param string $code
     * @param int    $life
     *
     * @return string
     * @throws \Exception
     */
    private function genHash(string $code, int $life): string
    {
        if ($this->redis instanceof \Redis) {
            //Store in Redis
            $key_hash = hash('md5', uniqid((string)(microtime(true) * mt_rand()), true));
            $key_name = self::KEY_PREFIX . $key_hash;

            if (!$this->redis->setnx($key_name, $code)) {
                return $this->genHash($code, $life);
            }

            $this->redis->expire($key_name, $life);
            unset($key_name);
        } else {
            //Store in client
            $key_hash = $this->lib_crypt->sign(json_encode(['hash' => &$code, 'life' => time() + $life]));
        }

        unset($code, $life);
        return $key_hash;
    }

    /**
     * Extract code from code hash
     *
     * @param string $hash
     *
     * @return string
     * @throws \Exception
     */
    private function getCode(string $hash): string
    {
        if ($this->redis instanceof \Redis) {
            //Store in Redis
            $key_name = self::KEY_PREFIX . $hash;
            $key_code = (string)$this->redis->get($key_name);
            $this->redis->del($key_name);
            unset($key_name);
        } else {
            //Store in client
            if ('' === $res = $this->lib_crypt->verify($hash)) {
                return '';
            }

            $json = json_decode($res, true);

            if (is_null($json) || !isset($json['life']) || !isset($json['hash']) || $json['life'] < time()) {
                return '';
            }

            $key_code = &$json['hash'];
            unset($res, $json);
        }

        unset($hash);
        return $key_code;
    }

    /**
     * Build mixed codes (letters & numbers)
     *
     * @return array
     */
    private function buildMix(): array
    {
        $result = [];

        $list = array_merge(range('A', 'Z'), range(0, 9));

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = $list[mt_rand(0, 35)];
        }

        $result['hash'] = implode($result['char']);

        unset($list, $i);
        return $result;
    }

    /**
     * Build pure number codes
     *
     * @return array
     */
    private function buildNum(): array
    {
        $result = [];

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = (string)mt_rand(0, 9);
        }

        $result['hash'] = implode($result['char']);

        unset($i);
        return $result;
    }

    /**
     * Build English letter codes
     *
     * @return array
     */
    private function buildWord(): array
    {
        $result = [];

        $list = range('A', 'Z');

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = $list[mt_rand(0, 25)];
        }

        $result['hash'] = implode($result['char']);

        unset($list, $i);
        return $result;
    }

    /**
     * Build Math plus codes
     *
     * @return array
     */
    private function buildPlus(): array
    {
        $result = [];

        //Generate numbers
        $number = [mt_rand(0, 9), mt_rand(10, 99)];

        //Plus tow numbers
        $result['hash'] = (string)($number[0] + $number[1]);

        //Add number chars
        $result['char'][] = (string)$number[$i = mt_rand(0, 1)];
        $result['char'][] = '+';
        $result['char'][] = (string)$number[0 === $i ? 1 : 0];

        //Add suffix
        $result['char'][] = '=';

        unset($num_1, $num_2);
        return $result;
    }

    /**
     * Build Math calculation codes
     *
     * @return array
     */
    private function buildCalc(): array
    {
        $result = [];

        //Allowed calculate options
        $option = ['+', '-', '*'];

        //Generate random numbers and option indicators
        $result['char'][] = mt_rand(0, 9);
        $result['char'][] = mt_rand(0, 2);
        $result['char'][] = mt_rand(0, 9);
        $result['char'][] = mt_rand(0, 2);
        $result['char'][] = mt_rand(0, 9);

        //Calculate function
        $calc = static function (int $num_1, int $opt, int $num_2): int
        {
            switch ($opt) {
                case 2:
                    $res = $num_1 * $num_2;
                    break;
                case 1:
                    $res = $num_1 - $num_2;
                    break;
                default:
                    $res = $num_1 + $num_2;
                    break;
            }

            unset($num_1, $opt, $num_2);
            return $res;
        };

        //Calculate result
        switch ($result['char'][1] <=> $result['char'][3]) {
            case -1:
                $result['hash'] = (string)$calc($result['char'][0], $result['char'][1], $calc($result['char'][2], $result['char'][3], $result['char'][4]));
                break;
            default:
                $result['hash'] = (string)$calc($calc($result['char'][0], $result['char'][1], $result['char'][2]), $result['char'][3], $result['char'][4]);
                break;
        }

        //Change number integer to string
        $result['char'][0] = (string)$result['char'][0];
        $result['char'][2] = (string)$result['char'][2];
        $result['char'][4] = (string)$result['char'][4];

        //Change option indicator to option string
        $result['char'][1] = $option[$result['char'][1]];
        $result['char'][3] = $option[$result['char'][3]];

        //Add suffix
        $result['char'][] = '=';

        unset($option, $calc);
        return $result;
    }
}