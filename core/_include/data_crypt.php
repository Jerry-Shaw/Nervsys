<?php

/**
 * Data Encrypt/Decrypt Module
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
class data_crypt
{


    //对方提供密钥和加密方式，本地生成uuid绑定返回数据


    const method = [
        'AES-128-CBC',
        'AES-128-CFB',
        'AES-128-CFB1',
        'AES-128-CFB8',
        'AES-128-ECB',
        'AES-128-OFB',
        'AES-192-CBC',
        'AES-192-CFB',
        'AES-192-CFB1',
        'AES-192-CFB8',
        'AES-192-ECB',
        'AES-192-OFB',
        'AES-256-CBC',
        'AES-256-CFB',
        'AES-256-CFB1',
        'AES-256-CFB8',
        'AES-256-ECB',
        'AES-256-OFB'
    ];


}