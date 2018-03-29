<?php

/**
 * Auth Code Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2018 秋水之冰 <27206617@qq.com>
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

class crypt_code extends crypt
{
    //Font filename (Place font files in "/ext/font/" folder)
    public static $font = 'font.ttf';

    //Auth code type (any / num / calc / word)
    public static $type = 'any';

    //Character count (only work for "num" & "word")
    public static $count = 4;

    //Image size
    public static $width  = 120;
    public static $height = 40;

    //Lifetime (in seconds)
    public static $life = 60;

    /**
     * Generate any codes from num / calc / word
     *
     * @return array
     */
    private static function gen_any(): array
    {
        $list = ['num', 'calc', 'word'];
        $type = $list[mt_rand(0, 2)];

        unset($list);

        return forward_static_call([__CLASS__, 'gen_' . $type]);
    }

    /**
     * Generate pure number codes
     *
     * @return array
     */
    private static function gen_num(): array
    {
        $result = [];
        for ($i = 0; $i < self::$count; ++$i) $result['char'][] = (string)mt_rand(0, 9);
        $result['code'] = implode($result['char']);

        unset($i);
        return $result;
    }

    /**
     * Generate Math calculation codes
     *
     * @return array
     */
    private static function gen_calc(): array
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
                $result['code'] = (string)$calc($result['char'][0], $result['char'][1], $calc($result['char'][2], $result['char'][3], $result['char'][4]));
                break;
            default:
                $result['code'] = (string)$calc($calc($result['char'][0], $result['char'][1], $result['char'][2]), $result['char'][3], $result['char'][4]);
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

        //Free memory
        unset($option, $calc);
        return $result;
    }

    /**
     * Generate English letter codes
     *
     * @return array
     */
    private static function gen_word(): array
    {
        $result = [];

        $list = range('A', 'Z');
        for ($i = 0; $i < self::$count; ++$i) $result['char'][] = $list[mt_rand(0, 25)];
        $result['code'] = implode($result['char']);

        unset($i);
        return $result;
    }

    /**
     * Get Auth Code values
     *
     * @return array
     */
    public static function get(): array
    {
        //Check Auth Code type
        if (!in_array(self::$type, ['any', 'num', 'calc', 'word'], true)) self::$type = 'any';

        //Generate Auth Code
        $codes = forward_static_call([__CLASS__, 'gen_' . self::$type]);

        //Encrypt result with lifetime
        $codes['code'] = parent::sign(json_encode(['code' => $codes['code'], 'life' => time() + (0 < self::$life ? self::$life : 60)]));

        //Image properties
        $font_file = __DIR__ . '/font/' . self::$font;

        $font_height = (int)(self::$height / 1.6);
        $font_width = (int)(self::$width / count($codes['char']));
        $font_size = $font_width < $font_height ? $font_width : $font_height;

        $top_padding = (int)(self::$height - (self::$height - $font_size) / 1.8);
        $left_padding = (int)((self::$width - $font_size * count($codes['char'])) / 2);

        //Create image
        $image = imagecreate(self::$width, self::$height);

        if (false === $image) {
            debug(__CLASS__, 'Auth Code Image create failed!');
            return ['err' => 1, 'msg' => 'Image create failed!'];
        }

        //Fill image in white
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        $colors = [];

        //Generator random colors
        for ($i = 0; $i < 255; ++$i) {
            $color = imagecolorallocate($image, mt_rand(0, 180), mt_rand(0, 180), mt_rand(0, 180));
            if (false !== $color) $colors[] = $color;
        }

        $color_index = count($colors) - 1;

        //Draw text
        foreach ($codes['char'] as $text) {
            imagettftext($image, (int)($font_size * mt_rand(88, 112) / 100), mt_rand(-18, 18), $left_padding, $top_padding, $colors[mt_rand(0, $color_index)], $font_file, $text);
            $left_padding += $font_size;
        }

        unset($codes['char'], $text);

        //Draw arcs
        for ($i = 0; $i < 5; ++$i) imagearc($image, mt_rand(0, self::$width), mt_rand(0, self::$height), mt_rand(0, self::$width), mt_rand(0, self::$height), mt_rand(0, 360), mt_rand(0, 360), $colors[mt_rand(0, $color_index)]);

        //Draw pixels
        for ($i = 0; $i < 500; ++$i) imagesetpixel($image, mt_rand(0, self::$width), mt_rand(0, self::$height), $colors[mt_rand(0, $color_index)]);

        unset($colors, $color_index, $i);

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

        unset($font_file, $font_height, $font_width, $font_size, $top_padding, $left_padding, $image);
        return $codes;
    }

    /**
     * Validate Auth Code & Input values
     *
     * @param string $code
     * @param string $input
     *
     * @return bool
     */
    public static function valid(string $code, string $input): bool
    {
        $res = parent::verify($code);
        if ('' === $res) return false;

        $json = json_decode($res, true);
        if (is_null($json) || !isset($json['life']) || !isset($json['code'])) return false;

        $result = $json['life'] > time() && $json['code'] === strtoupper($input) ? true : false;

        unset($code, $input, $res, $json);
        return $result;
    }
}