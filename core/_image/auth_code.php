<?php

/**
 * Authority Code Image
 * Version 2.7.0 (Nerve Cell)
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 *
 * Copyright 2015-2016 Jerry Shaw
 *
 * This file is part of ooBase Core.
 *
 * ooBase Core is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ooBase Core is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ooBase Core. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types = 1);

require __DIR__ . '/../_include/cfg.php';

$operator = ['+', '*'];

$codes = [];
$codes[] = (string)mt_rand(0, 9);
$codes[] = $operator[mt_rand(0, 1)];
$codes[] = (string)mt_rand(0, 9);
$codes[] = $operator[mt_rand(0, 1)];
$codes[] = (string)mt_rand(0, 9);

$auth_code = (string)eval('return ' . implode($codes) . ';');

$codes[] = '=';
$codes[] = '?';

$width = 240;
$height = 66;
$font = 'georgiab.ttf';
$font_size = 36;
$left_padding = 16;

$image = imagecreate($width, $height);

imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

$colors = [];

for ($i = 0; $i < 255; ++$i) {
    $color = imagecolorallocate($image, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200));
    if (false !== $color) $colors[] = $color;
}

$color_index = count($colors) - 1;

foreach ($codes as $text) {
    imagettftext($image, $font_size, mt_rand(-18, 18), $left_padding, 44, $colors[mt_rand(0, $color_index)], $font, $text);
    $left_padding += 30;
}

for ($i = 0; $i < 10; ++$i) imagearc($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, 360), mt_rand(0, 360), $colors[mt_rand(0, $color_index)]);

for ($i = 0; $i < 2000; ++$i) imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $colors[mt_rand(0, $color_index)]);

ob_clean();
ob_start();

header('Content-type: image/gif');

imagegif($image);
imagedestroy($image);

load_lib('core', 'db_redis');
\db_redis::$redis_db = 0;
$db_redis = \db_redis::connect();
$db_redis->set(get_uuid(get_client_info()['ip']), $auth_code, 180);

ob_flush();
flush();