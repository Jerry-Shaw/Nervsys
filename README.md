# Nervsys

README: [English](README.md) | [简体中文](README_zh-CN.md)

[![release](https://img.shields.io/badge/release-8.0.0-blue?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/releases)
[![issues](https://img.shields.io/github/issues/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/issues)
[![contributors](https://img.shields.io/github/contributors/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/graphs/contributors)
[![last-commit](https://img.shields.io/github/last-commit/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/commits/master)
[![license](https://img.shields.io/github/license/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/blob/master/LICENSE.md)

## About Nervsys

* What is "Nervsys"?  
  A very slight PHP framework, very easy to use and integrate.

* Why called "Nervsys"?  
  At the very beginning, as we hoped. The unit could process more like a nerve cell, and build together as a pure
  data-based calling system. Exact commands would be unnecessary to tell the system what to do.

* Any short name?  
  **NS**, that's what most of us call it, but, don't mix it up with Nintendo Switch.

* Requirements:  
  PHP **7.4+** and above. Any kind of web server or running under CLI mode.

* Usage example:
    1. Ordinary framework for Web-backend-developing
    2. API controller for all types of Apps
    3. Client for program communication
    4. More...

## Installation

1. Clone or download source code to anywhere on your machine. Only one copy is required on the same machine even
   multiple projects exist.
2. Include "NS.php" in the main entry script of the project, and call it with using "NS::new();".
3. If needed, using "/Ext/libCoreApi" to register your own modules and functions before calling "NS::new();".
4. Write your API code classes under "/api", application code classes under "/app", if not changed, and there you go.
5. In "/Ext", there are common useful extensions for normal project development, so, please do review them when coding.
   They can be helpful.

## Usage

###### Notice: All demo usage is under default system settings.

#### 1. Suggested project structure

```text
Root/
    ├─api/                            default api entry code path
    │    └─DemoApiClass.php           demo api php class file
    ├─app/                            default application code path
    │    └─DemoAppClass.php           demo application php class file
    ├─config/                         suggested conf file path (use "Ext/libConfGet.php" to process)
    │       ├─dev.conf                conf file for dev
    │       ├─prod.conf               conf file for prod
    │       └─...                     other conf files
    ├─message/                        suggested message file path (use "Ext/libErrno.php" to process)
    │        └─msg.ini                custom message ini file
    └─home/                           default home path
          └─index.php                 main entry script
```

#### 2. NS integration

Follow "[Installation](#installation)" steps to integrate NS into your entry script. Demo code is as follows.

```php
require __DIR__ . '/../../NervSys/NS.php';

//optional, if needed, please review "Ext/libCoreApi.php"
\Ext\libCoreApi::new()
    //open core debug mode (error display with results)
    ->setCoreDebug(true)
    //open CORS to all with default headers
    ->addCorsRecord('*')
    //set output content type to "application/json; charset=utf-8"
    ->setContentType('application/json');

NS::new();
```

#### 3. Request data format

NS can parse data from both FormData and request Payload via GET or POST.  
When data is sending as request Payload, both JSON and XML are supported.  
Data fetcher and parser library in NS is "/Core/Lib/IOUnit.php".

In HTTP request, NS fetch and parse data in the following steps:

```text
1. read Accept from HTTP request header, decide return type if not defined in entry.
2. read URl, try to fetch "c" from "PATH_INFO" or "REQUEST_URI" if found.
3. fetch HTTP FormData in non-overwrite mode in following order: FILES -> POST -> GET.
4. fetch request Payload, and try to decode in JSON/XML format, add to data from above.
5. read HTTP Header and Cookie data by specific keys defined in entry script, add to data from above.
6. find and isolate "c" data from data source, and pass it to Router library as request command.
```

In CLI mode, NS takes "c" from "-c" parameter, or the first argument if not found. String parameter "-d" will be taken to
decode to get CGI data source. "-r" forces output returned data format. Other arguments will be considered as CLI argv.

#### 4. About key "c"

"c" in request data will be taken as request command, and will lead system to go continue.  
"c" can be passed in any ways, URL, GET, POST, all is OK, no matter FormData or request Payload.

In CGI mode, normally known as HTTP request, "c" is always redirected to api path for some security reasons, but, CLI
mode allows calling from root by adding "/" in the beginning of "c" using full class namespace path.

Valid "c" format should be as follows:

```text
API path based: innerpath_in_api_path/class_name/public_method_name

examples:
URL: http://your_domain/index.php/user/login => calling "login" method in "\api\user" class.
URL: http://your_domain/index.php/user/info/byId => calling "byId" method in "\api\user\info" class.

GET: http://your_domain/index.php?c=user/login
POST: pass directly "user/login" in "c" parameter, both support FormData or request Payload.
```

```text
ROOT path based: /namespace/class_name/public_method_name

examples:
URL: NOT support.

CLI: php index.php /app/user/login => calling "login" method in "\app\user" class.
CLI: php index.php -c"/app/user/login" => calling "login" method in "\app\user" class.

GET: http://your_domain/index.php?c=/app/user/login
POST: pass directly "/app/user/login" in "c" parameter, both support FormData or request Payload.
```

#### 5. Data autofill

Once "c" and data source are taken by system, Router library and Execute library will be woken up to run exact method.
Key matched parameters will be taken out of data source, and pass in the right order automatically into target method
when calling. Watch out of all data passed to NS, keys are case-sensitive, and data values are type-strict. All returned
results will be captured and output.

example:

```text
parameters in any order:
URL: http://your_domain/index.php/user/login?name=admin&passwd=admin&age=30&type=client
URL: http://your_domain/index.php/user/login?passwd=admin&age=30&name=admin&type=client
```

* API 1

```php
namespace api;

class user
{
    public function login($name, $passwd)
    {
        //your code

        return $name . ' is online!';
    }
}
```

* API 2

```php
namespace api;

class user
{
    public function login($name, $passwd, int $age)
    {
        //your code

        return $name . ' is ' . $age . ' years old.';
    }
}
```

#### 6. Exposed Core libraries

NS leaves some important core libraries exposed to developers since 8.0 and on.  
Thanks to [douglas99](https://github.com/douglas99), all changeable core related APIs are merged into "
Ext/libCoreApi.php".  
With this, developers can register own libraries instead of default ones, such as custom Router, outputHandler, ApiPath,
hook related functions, etc...

## Todo

- [x] Basic Core and Ext logic
- [x] Automatic argument mapping
- [x] App code env detection logic
- [x] Custom router module support
- [x] Custom error handler module support
- [x] Custom data reader/output module support
- [x] Path based hook registration function support
- [ ] Socket related functions
- [ ] ML/AI based internal router
- [ ] More detailed documents and demos

Except functions listed above, NS still has a long way to go.  
Thanks for issues & pull requests if you found bugs or make it better, or just need help. Contact us.

## Supporters

Thanks to [JetBrains](https://www.jetbrains.com/?from=Nervsys) for supporting the project, within the Open Source
Support Program.

## License

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the [LICENSE.md](https://github.com/Jerry-Shaw/NervSys/blob/master/LICENSE.md)
file.