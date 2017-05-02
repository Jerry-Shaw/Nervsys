<?php

/**
 * HTTP Request Module
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
class ctrl_http
{
    //Request URL
    public static $url = '';


    public static $cookie = '';
    public static $key = '';
    public static $data = '';
    public static $method = '';
    public static $protocol = '';
    public static $user_agent = 'Mozilla/5.0 (Compatible; NervSys Data API 1.0.0; Permission Granted by NervSys Data Center)';


    public static function init()
    {
        //Process URL


        $url_parts = parse_url(self::$url);


    }


    public static function get()
    {

    }


    public static function post()
    {

    }


    public static function upload()
    {

    }


}