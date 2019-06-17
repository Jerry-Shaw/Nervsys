# Nervsys

Stable version: 7.2.20  
Extension version: 2.0  
[Chinese Documents](https://github.com/NervSys/NervSys/blob/master/README_zh.md)  
[Unit Test Suites](https://github.com/NervSys/tests)  
  
Report to us if you encounter an issue.  
Pull request when you have better ideas.  
Thanks for your support.  

## About

* What is Nervsys?  
A very slight framework based on PHP7.2+ for universal API controlling.  

* Why called "Nervsys"?  
At the very beginning, as we hoped. The unit could process more like a nerve cell, and build together as a pure data-based calling system. Exact commands would be unnecessary to tell the system what to do.  

* Requirements:  
PHP 7.2+ and above. Any kind of web server or running under CLI mode.  

* Usage examples:  
    1. Ordinary framework for Web-developing
    2. API controller for all types of Apps
    3. Client for program communication
    4. More...


## Structure

    /
    ├─core/
    │     ├─handler/
    │     │        ├─platform/
    │     │        │         ├─lib/
    │     │        │         │    └─os.php      OS interface
    │     │        │         ├─linux.php        linux OS handler
    │     │        │         ├─winnt.php        winnt OS handler
    │     │        │         └─...
    │     │        ├─error.php                  error handler
    │     │        ├─factory.php                factory handler
    │     │        ├─operator.php               operator handler
    │     │        └─platform.php               platform handler
    │     ├─helper/
    │     │       └─log.php                     log helper
    │     ├─parser/
    │     │       ├─cmd.php                     command parser
    │     │       ├─data.php                    data parser
    │     │       ├─input.php                   input data parser
    │     │       ├─output.php                  output data parser
    │     │       └─trustzone.php               TrustZone data parser
    │     ├─system.ini                          system setting file
    │     └─system.php                          system script file
    ├─ext/
    │    ├─font/
    │    ├─lib/
    │    │    └─key.php                         keygen interface
    │    ├─conf.php                             Config file extension
    │    ├─crypt.php                            Encrypt/decrypt extension
    │    ├─crypt_img.php                        Auth code image extension from crypt
    │    ├─doc.php                              API Doc extension
    │    ├─errno.php                            Error code extension
    │    ├─file.php                             Filesystem related IO extension
    │    ├─http.php                             HTTP request extension
    │    ├─image.php                            Image processing extension
    │    ├─keygen.php                           keygen extension for crypt
    │    ├─lang.php                             Language pack extension
    │    ├─memcached.php                        Memcached extension (Thanks to tggtzbh)
    │    ├─misc.php                             Misc functions
    │    ├─mpc.php                              Multi-Process Controller Extension
    │    ├─pdo.php                              PDO connector extension
    │    ├─pdo_mysql.php                        PDO MySQL extension (Thanks to kristenzz)
    │    ├─provider.php                         Object extends provider extension
    │    ├─redis.php                            Redis connector extension
    │    ├─redis_cache.php                      Redis cache extension from Redis
    │    ├─redis_lock.php                       Redis lock extension from Redis
    │    ├─redis_queue.php                      Redis queue extension from Redis
    │    ├─redis_session.php                    Redis session extension from Redis
    │    ├─socket.php                           Socket extension
    │    ├─upload.php                           Upload extension
    │    └─...
    ├─logs/
    └─api.php                                   API entry script (can be placed into /app or anywhere)


## About api.php

"api.php" is the default entry script of Nervsys. But it is only an entry of a site, and can be placed anywhere as needed.  
We strongly suggest that, create a new entry file under the main app path by modifying the require path, then set the site root to main app path also, to fully avoid exposing project structure to outside.  


## Reserved Words
  
CGI: c/cmd, m/mime  
CLI: c/cmd, m/mime, d/data, p/pipe, t/time, r/ret  
  
Explanations:  
r/ret: Return option (No needed value)  
c/cmd: System commands (User defined)  
m/mime: Output MIME type (json/xml/html, UTF-8, default: json)  
d/data: CLI Data package (Transfer to CGI progress)  
p/pipe: CLI pipe data package (Transfer to CLI programs)  
t/time: CLI read timeout (in microsecond, default: 0, wait till done)  
  
The words above are reserved by NervSys core. So that, they should be taken carefully when using.


## Config "system.ini"

"system.ini" locates right under the "core" folder, which contains most of the important setting sections.  
Always remember, do NOT delete any entry or section from "system.ini".  

### SYS

    [SYS]
    ; TimeZone
    timezone = PRC
    
    ; Application directory name
    ; Set to empty if all app folders laying right under root directory
    app_path = app
    
    ; Enable reading "cmd" content from URL
    cmd_in_url = on

    ; Enable/disable calling functions automatically when no specific function name was given
    auto_call_mode = on

### LOG

    [LOG]
    This section works for all.
    This section contains custom set log levels.
    
    emergency = on
    alert = on
    critical = on
    error = on
    warning = on
    notice = on
    info = on
    debug = on
    
    display = on ; Display logs on screen
    
    Don't delete these levels, just change the value to "off" or "0", if there is no need to log or display them.

### CGI

    [CGI]
    This section works for CGI process.
    This section holds all the short commands mapping values.
    
    Example & explain:
    
    seting: MyCMD = dirA/dirB/model-func
    usage: cmd=MyCMD
    explain: sending "cmd" with the value "MyCMD", it will be redirected to "dirA/dirB/model-func" instead
    
    seting: MyCMD = dirA/dirB/model
    usage: cmd=MyCMD-func
    explain: sending "cmd" with the value "MyCMD-func", "MyCMD" will be replaced to "dirA/dirB/model" instead
    
    seting: MyCMD = dirA/dirB
    usage: cmd=MyCMD/model-func
    explain: sending "cmd" with the value "MyCMD-func", "MyCMD" will be replaced to "dirA/dirB" instead


### CLI

    [CLI]
    This section works for CLI process.
    This section holds all the valid local program commands.
    
    Example & explain:
    
    seting: MyCMD = /xxx/path/mycmd
    usage: --cmd="MyCMD"
    explain: sending "cmd" with the value "MyCMD", "/xxx/path/mycmd" will be called instead
    
    seting: MyCMD = /xxx/path/mycmd -a -b --more
    usage: --cmd="MyCMD"
    explain: sending "cmd" with the value "MyCMD", "/xxx/path/mycmd -a -b --more" will be called instead
    
    Notice: Programs which are not configged in this section will not be called anyway.
    

### CORS

    [CORS]
    This section works for HTTP Request.
    This section holds Cross-Origin Resource Sharing settings.
    
    Example & explain:
    
    seting:
    http://your.domain.com = X-Requested-With, Content-Type, Content-Length
    https://your.domain.com = X-Requested-With, Content-Type, Content-Length
    http://your.domain.com:800 = X-Requested-With, Content-Type, Content-Length, Custom-Header

    explain:
    All requests via ajax from the domains above in the section are allowed, with the request headers accepted.

    NOTICE:
    If "*" is set in the keys, that means CORS will be opened to all incoming domains with defined headers accepted.
    
    
### INIT

    [INIT]
    This section works for all.
    This section holds system startup initial functions.
    
    Example & explain:
    
    Single setting:
    SomeDesc = dirA/dirB/model-func
    
    explain: 
    "\dirA\dirB\model::func($params)" will be called on startup with required agruments. 
    
    
    Multiple setting:
    DescA = dirA/dirA/model
    DescB = dirA/dirB/model-funcA
    DescC = dirA/dirB/model-funcB
    DescD[] = dirC/dirC/model-funcA
    DescD[] = dirC/dirC/model-funcB
    
    explain: 
    "\dirA\dirB\model::funcA($params)" & "\dirB\dirB\model::funcB($params)"
    will be called on startup with required agruments. 
    While, "\dirC\dirC\model::__construct($params)" will be called instead because of no function is set.
    
    Notice: 
    Calling in prepare state (S1), before input reading. 
    The keys in "INIT" section have no means for system, but for developers to know what they are for. 
    "__construct" will be called if no function has been specified in the values.
    Array setting is allowed in this section. 
    Required arguments will be automatically passed. 
    All returned values will be captured when exist. 

    Suggest: 
    Don't always return unless necessary. 
    
### LOAD

    [LOAD]
    This section works for all.
    This section holds "/subfolder/" startup initial functions.
    When "app_path" entry was set, sub-folders are related to the "app_path" instead
    
    Example & explain:
    
    Single setting:
    dirA = dirB/model-func
    
    explain: 
    "\dirB\model::func($params)" will be called only once right before calling functions under dirA. 
    
    
    Multiple setting:
    dirA = dirX/model-func
    dirB[] = dirX/model-funcA
    dirB[] = dirX/model-funcB
    dirC = dirY/model
    
    explain: 
    "\dirX\model::func($params)" will be called only once right before calling functions under dirA.
    "\dirX\model::funcA($params)" & "\dirX\model::funcB($params)" both will be called only once right 
    before calling functions under dirB.
    While, "\dirY\model::__construct($params)" will be called instead, because of no function is set, 
    only once right before calling functions under dirC.
    
    Notice: 
    Calling in process state (S2), on accessing first level sub-folders. 
    The keys in "LOAD" section point to the first level sub-folders, while the setting values 
    point to the functions which will be called when the sub-folder is being accessed. 
    "__construct" will be called if no function has been specified in the values.
    Array setting is accepted to call multiple functions. 
    Required arguments will be automatically passed. 
    All returned values will be captured when exist. 

    Suggest: 
    Don't always return unless necessary. 


### PATH

    [PATH]
    This section works for autoload function.
    This section contains custom paths to include.
    Only works for non-namespace-class include paths.
    
    Some example:
    
    1 = pathA ; Relative path to ROOT
    2 = pathA/pathB/ ; Relative path to ROOT
    3 = pathA\pathB ; Relative path to ROOT
    4 = pathA\pathB\ ; Relative path to ROOT
    5 = /pathB/ ; Absolute path
    6 = /pathA/pathB ; Absolute path
    7 = /pathA/pathB/ ; Absolute path
    8 = \pathA\pathB ; Absolute path
    9 = \pathA\pathB\ ; Absolute path
    svr_path = D:\server\lib ; Absolute path
    lib_path = E:\lib\ ; Absolute path
    some_key = F: ; Absolute path
    ...

    Notice: 
    The last "/" of the path is not required.  
    The keys has no means for system, but for developers to know what they are for.  
    

## Examples
    
### Example structures:

    root/
        ├─DirA/
        │     ├─ctr/
        │     │    └─TestA.php  TestA script
        │     └─TestB.php       TestB script
        ├─DirB/
        │     ├─model_a.php     model_a script
        │     ├─model_b.php     model_b script
        │     ├─model_c.php     model_c script
        │     └─....php         More script
        └─.../


### Example "TestA.php":

```php
<?php
namespace DirA\ctr;

class TestA
{
    public static $tz = [
        'test_a' => 'param_a,param_b',
        
        'test_b' => [
                'pre' => 'DirA/classA-funcA,DirB/classA-funcA',
                'post' => 'DirA/classB-funcA,DirB/classB-funcA',
                'param' => 'param_c,param_d'
            ],
        
        'test_c' => [
                'param' => 'param_a,param_b,param_c'
            ],
            
        'test_d' => [
                'post' => 'DirA/classB-funcA'
            ]
    ];
    
    public static function test_a(string $param_a, array $param_b): void
    {
        //some code...
    }
    
    public static function test_b(string $param_c, array $param_d): void
    {
        //some code...
    }
    
    public function test_c(string $param_a, array $param_b, string $param_c): string
    {
        //some code...
    }
    
    public function test_d(string $param_a, array $param_b, string $param_c): array
    {
        //some code...
    }
}
```


### Example "TestB.php":

```php
<?php
namespace DirA;

class TestB
{
    public $tz = 'test_a,test_b,test_c';
    
    public static function test_a(string $param_a, array $param_b): int
    {
        //some code...
    }
    
    public static function test_b(string $param_c, array $param_d): object
    {
        //some code...
    }
    
    public function test_c(): bool 
    {
        //some code...
    }
}
```


## Keywords

### error_reporting

"error_reporting" in NervSys does not matter too much any more.  
All errors and exceptions will be handled well into logs and/or showing up.  
But it holds the exit level when an error occurred and the format of JSON/XML result.  
When "error_reporting" is set LARGER than 0, line breaks will be added to JSON/XML result.


### Factory
  
In the latest version of NervSys, "factory" handler is ready for use to control all extended classes.  
  
Usage:  
All classes extended from "factory" can be used as follows:  
  
* Create new cloned class from called class:  
$class = class_name::new(arguments, ...);  
  
* Save it under alias name:  
$class = class_name::new(arguments, ...)->as('alias_name');  
  
* Save it under alias name with configurations:  
$class = class_name::new(arguments, ...)->config(array $settings)->as('alias_name');  
  
* Get cloned object by its alias name:  
$cloned_class = class_name::use('alias_name');  
  
* Save an object in the middle way:  
$object->as('alias_name'); or $object->config(array $settings)->as('alias_name'); 
  
* Free from factory:  
$object->free(); or $object->free('alias_name');  
  
  
NOTICE: Same ways to call "use" and "obtain" methods, but there are still some small differences:  
  
**new**: returned object points to the called class, but in clone mode. Arguments are the params for "__construct" method.  
  
**use**: returned object points to the called class, directly to the original instance saved by "as" method, not cloned. One argument is expected, its alias name.  
  
**obtain**: returned object points to a class passed by the first argument as a class name, with the second argument as the params for "__construct" method.  
  
  
**Caution**:  
1. Make sure to use alias names conditionally and differently to avoid conflict.  
2. Make sure the calling sequence is as expected, especially, check the sub-calling entries in all "__construct" methods when using factory to new/use classes. Incorrect object will be generated by factory even if a "new" is calling apparently before an "use", while the "use" is calling inside "__construct" of a class being called before the "new".  
  
  
### TrustZone

Every class which is exposed to API should always contain a variable named "$tz", static or non-static are both supported. It controls the exact method calling behaviour in the owner class.  
  
The values are recorded when API accesses the class for the first time. Never try to modify "$tz" in any of the functions in the same class. Nothing will be affected.  
  
Keys in "$tz": Functions that are exposed to API.  
Values in "$tz": Control API action to call target function.  
  
In "$tz", keys of "pre", "post" and "param" can be combined when needed, actions will be proceeded as follows:  
1. Execute all preset functions in values of "pre".  
2. Execute target method according to "param" settings.  
3. Execute all preset functions in values of "post".  
  
Functions that are not listed in "$tz" won't be called by API directly.  
  
When input data mismatched preset values, API will skip calling the target function and its "post" dependency and throw out a warning exception.  
  
Once anything failed during TrustZone process cycle, such as, "pre"/"post" dependency method missing, function argument missing, etc. API will skip the rest of the process cycle and throw out a warning exception. Other process cycles will continue running.  
  
_Two types of $tz in array format: example of "TestA.php"_  
  
In the example above, $tz for function "test_a" is written in simple mode, while, $tz for function "test_b" is written in full mode.  
  
In simple mode, the contents are the MUST exist parameters for the function. API will ignore those functions when input data structure is not matched with $tz settings.  
  
In full mode, MUST exist parameters are listing under 'param' key, they are doing the same thing. 'pre' key controls the pre-run methods, while 'post' key controls the post-run method. The two settings are executed before/after the function's calling.  
  
_Simple $tz in string format: example of "TestB.php_  

That is a simple format which means methods "test_a", "test_b", "test_c" are all exposed to API with no TrustZone limitation. But they will be still checked by the argument data parser.  
  
NOTICE: We can set the value of TrustZone to "*" to simply expose all public methods to API.  
  
  
### Autofill

Parameters in functions will be automatically filled by API once existing in process data with the same name. Note that, this feature only works for API exposed functions. Once the function is being called, both $tz and params will be checked for qualification to pass.


### Simple-Calling

As one of the basic support features, works good on web development.  
  
Method: GET, POST  
DataType: application/x-www-form-urlencoded, application/json, application/xml, multipart/form-data  
  
GET examples: 
api.php?cmd=DirA/ctr/TestA-test_a&param_a=xxx&param_b[]=yyy&param_b[]=zzz
api.php?cmd=DirA/ctr/TestA-test_b&param_c=xxx&param_d[]=yyy&param_d[]=zzz
api.php?cmd=DirA/ctr/TestA-test_c&param_a=xxx&param_c=xxx&param_b[]=yyy&param_b[]=zzz


### Multiple-Calling & Judging-Calling

As said, it is expected to process data like a nerve cell, Multiple-Calling & Judging-Calling MUST be supported.  
  
Method: GET, POST  
DataType: application/x-www-form-urlencoded, application/json, application/xml, multipart/form-data  
  
Let's suppose that, "TestB.php" in example has the functions and $tz with allowed parameters API needs below.


**Multiple-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Result:  
All REQUESTED functions that qualified both $tz & param checking will be called. All result data package will be put right under corresponding function name keys with class names as prefix. 


**Judging-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-DirA/TestB&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Result:  
All functions IN $tz will be looked over. Those ones which qualified both $tz & param checking will be called. All result data package will be put right under corresponding function name keys with class names as prefix.  

Notice:  
This calling method is controlled by "auto_call_mode" setting in "system.ini" for some secure issues.  


## Command Line

In this mode, PHP script will be called when the command and data matches the class & function structure. External programs listed in [CLI] section will also be called when command is qualified. Both results can be captured and compound under their key names.


### CLI options

    r/ret: Return option (Available in CLI executable mode only)
    c/cmd: System commands (separated by "-" when multiple)
    m/mime: Output MIME type (json/xml/html, default: json, available when "r/ret" is set)
    d/data: CLI Data package (Transfer to CGI progress)
    p/pipe: CLI pipe data package (Transfer to CLI programs)
    t/time: CLI read timeout (in microsecond, default: 0, wait till done)


### CLI usage

**Different option example: The two work the same.**

* /path/php api.php -r -c "DirA/ctr/TestA-test_a" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz"


**More examples:**

* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-test_b-test_c" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA-DirA/TestB" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"


**External calling examples:**

* /path/php api.php --cmd "MyCMD" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "MyCMD" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "MyCMD_A-MyCMD_B" --pipe "xxxxxxxx" --time "1000"  


**Mixed calling examples:**

* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-MyCMD" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c-MyCMD_A" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-MyCMD_A-MyCMD_B" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz" --pipe "xxxxxxxx" --pipe "xxxxxxxx" --time "1000"  
  

## On Error

Normally, when php encounters an error, or an exception, it'll stop anyway. But here, it only stops on ERROR, even if dependency functions are missing, or exception which is not set Code to E_USER_ERROR, etc... Very useful when doing multiple calling (not finally done yet)  
  

## Credits

[kristenzz](https://github.com/kristemZZ)  
[tggtzbh](https://github.com/tggtzbh)  
[xushuhui](https://github.com/xushuhui)  


## Supporters

Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project, within the Open Source Support Program.  


## Licensing

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the LICENSE.md file.
