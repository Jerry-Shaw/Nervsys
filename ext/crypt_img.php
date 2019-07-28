<?php

/**
 * Crypt Image Extension
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

class crypt_img extends crypt
{
    //Predefined types
    const TYPE_MIX  = 'mix';
    const TYPE_NUM  = 'num';
    const TYPE_WORD = 'word';
    const TYPE_PLUS = 'plus';
    const TYPE_CALC = 'calc';

    //Support types
    const TYPES = ['mix', 'num', 'word', 'plus', 'calc'];

    //Code output types
    protected $type = [];

    //Lifetime (in seconds)
    protected $life = 60;

    //Image size (in pixels)
    protected $width  = 120;
    protected $height = 40;

    //Length (only works for "num" & "word")
    protected $length = 6;

    //Font filename (stored in "/ext/font/")
    protected $font = 'font.ttf';

    /**
     * Get Code
     *
     * @return array
     * @throws \Exception
     */
    public function get(): array
    {
        //Validate code types
        $type = !empty($type) ? array_intersect($type, self::TYPES) : self::TYPES;

        //Generate Auth Code
        $codes = $this->{'build_' . $type[mt_rand(0, count($type) - 1)]}();

        //Encrypt result with lifetime
        $codes['code'] = parent::sign(json_encode(['code' => $codes['code'], 'life' => time() + (0 < $this->life ? $this->life : 60)]));

        //Image properties
        $font_file = __DIR__ . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR . $this->font;

        //Calculate image canvas
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
                $font_file,
                $text
            );

            $left_padding += $font_size * strlen($text);
        }

        unset($codes['char'], $text);

        //Add arcs
        for ($i = 0; $i < 5; ++$i) {
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

        //Add noise
        for ($i = 0; $i < 500; ++$i) {
            imagesetpixel(
                $image,
                mt_rand(0, $this->width),
                mt_rand(0, $this->height),
                $colors[mt_rand(0, $color_index)]
            );
        }

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

        unset($type, $font_file, $font_height, $font_width, $font_size, $top_padding, $left_padding, $image);
        return $codes;
    }

    /**
     * Check Auth Code & Input values
     *
     * @param string $code
     * @param string $input
     *
     * @return bool
     * @throws \Exception
     */
    public function check(string $code, string $input): bool
    {
        $res = parent::verify($code);

        if ('' === $res) {
            return false;
        }

        $json = json_decode($res, true);

        if (is_null($json) || !isset($json['life']) || !isset($json['code'])) {
            return false;
        }

        $result = $json['life'] > time() && $json['code'] === strtoupper($input) ? true : false;

        unset($code, $input, $res, $json);
        return $result;
    }

    /**
     * Build mixed codes (letters & numbers)
     *
     * @return array
     */
    private function build_mix(): array
    {
        $result = [];

        $list = array_merge(range('A', 'Z'), range(0, 9));

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = $list[mt_rand(0, 35)];
        }

        $result['code'] = implode($result['char']);

        unset($list, $i);
        return $result;
    }

    /**
     * Build pure number codes
     *
     * @return array
     */
    private function build_num(): array
    {
        $result = [];

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = (string)mt_rand(0, 9);
        }

        $result['code'] = implode($result['char']);

        unset($i);
        return $result;
    }

    /**
     * Build English letter codes
     *
     * @return array
     */
    private function build_word(): array
    {
        $result = [];

        $list = range('A', 'Z');

        for ($i = 0; $i < (int)$this->length; ++$i) {
            $result['char'][] = $list[mt_rand(0, 25)];
        }

        $result['code'] = implode($result['char']);

        unset($list, $i);
        return $result;
    }

    /**
     * Build Math plus codes
     *
     * @return array
     */
    private function build_plus(): array
    {
        $result = [];

        //Generate numbers
        $number = [mt_rand(0, 9), mt_rand(10, 99)];

        //Plus tow numbers
        $result['code'] = (string)($number[0] + $number[1]);

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
    private function build_calc(): array
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

        unset($option, $calc);
        return $result;
    }
}