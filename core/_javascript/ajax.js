/**
 * Common JavaScript
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author 李盛青 <happyxiaohang@163.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
 * Copyright 2016 李盛青
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

var API = '/api.php';

function AJAX(object) {
    var url = object.url || API;
    var key = object.key || null;
    var data = object.data || null;
    var callback = object.callback || null;
    var dataType = object.dataType || 'json';
    var type = object.type ? object.type : (data ? 'POST' : 'GET');

    var HttpRequest = null;

    if (window.XMLHttpRequest) HttpRequest = new XMLHttpRequest();
    else if (window.ActiveXObject) {
        var Version = [
            'MSXML2.XMLHTTP.6.0',
            'MSXML2.XMLHTTP.5.0',
            'MSXML2.XMLHTTP.4.0',
            'MSXML2.XMLHTTP.3.0',
            'MSXML2.XMLHTTP.2.0',
            'Microsoft.XMLHTTP'
        ];

        var i, Versions = Version.length;

        for (i = 0; i < Versions; ++i) {
            try {
                HttpRequest = new ActiveXObject(Version[i]);
                break;
            } catch (e) {
                console.log(Version[i] + ' Not Support!');
            }
        }
    } else {
        console.log('AJAX Not Support!');
        return;
    }

    if (null !== HttpRequest) {
        var Query = null;
        if (null !== data) {
            if ('string' === typeof(data)) Query = data;
            else if ('object' === typeof(data)) {
                var Key, Queries = [];
                if (!Array.isArray(data)) for (Key in data) Queries.push(encodeURIComponent(Key) + '=' + encodeURIComponent(data[Key]));
                else for (Key in data) if ('string' === typeof(data[Key]['name'])) Queries.push(encodeURIComponent(data[Key]['name']) + '=' + encodeURIComponent(data[Key]['value']));
                Query = Queries.join('&');
            }
        }

        if ('GET' === type && null !== Query) {
            url += '?' + Query;
            Query = null;
        }

        HttpRequest.open(type, url, true);

        if (null !== key) HttpRequest.setRequestHeader('KEY', key);
        if ('POST' === type) HttpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        HttpRequest.onreadystatechange = function () {
            if (4 === HttpRequest.readyState) {
                if (200 === HttpRequest.status) {
                    if (null !== callback) callback('json' === dataType ? JSON.parse(HttpRequest.responseText) : HttpRequest.responseText);
                } else console.log('AJAX failed with HTTP Status Code: ' + HttpRequest.status);
            }
        };

        HttpRequest.send(Query);
    }
}