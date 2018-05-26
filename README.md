# Nervsys

Stable Version: 6.0.0


## About

* What is Nervsys?

A very slight framework based on PHP7.1+ for universal API controlling.  

* Why called "Nervsys"?

At the very beginning, as we hoped. The unit could process more like a nerve cell, and build together as a pure data-based calling system. Exact commands would be unnecessary to tell the system what to do.

* Requirements:

PHP7.1+ and above. Any kind of web server or running under CLI mode.  

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
    │     │        │         ├─darwin.php       darwin OS handler
    │     │        │         ├─linux.php        linux OS handler
    │     │        │         ├─winnt.php        winnt OS handler
    │     │        │         └─...
    │     │        ├─error.php                  error handler
    │     │        ├─logger.php                 logger handler
    │     │        ├─observer.php               observer handler
    │     │        ├─operator.php               operator handler
    │     │        └─platform.php               platform handler
    │     ├─parser/
    │     │       ├─cmd.php                     command parser
    │     │       ├─input.php                   input data parser
    │     │       ├─setting.php                 setting config parser
    │     │       └─trustzone.php               TrustZone data parser
    │     ├─pool/
    │     │     ├─config.php                    config data pool
    │     │     ├─order.php                     cmd order pool
    │     │     └─unit.php                      io unit data poll
    │     └─setting.ini                         setting config file
    └─ext/
         ├─font/
         ├─lib/
         │    └─key.php                         keygen interface
         ├─upload/
         │       ├─en-US/                       en-US language folder for upload
         │       ├─zh-CN/                       zh-CN language folder for upload
         │       └─upload.ini                   error code file for upload
         ├─crypt.php                            Encrypt/decrypt extension
         ├─crypt_code.php                       Auth Code extension from crypt
         ├─errno.php                            Error code extension
         ├─file.php                             Filesystem related IO extension
         ├─http.php                             HTTP request extension
         ├─image.php                            Image processing extension
         ├─keygen.php                           keygen extension for crypt
         ├─lang.php                             Language pack extension
         ├─memcached.php                        Memcached Extension
         ├─mpc.php                              Multi-Process Controller Extension
         ├─pdo.php                              PDO connector extension
         ├─pdo_model.php                        MySQL model extension from PDO
         ├─pdo_mysql.php                        MySQL extension from PDO
         ├─redis.php                            Redis connector extension
         ├─redis_cache.php                      Redis cache extension from Redis
         ├─redis_lock.php                       Redis lock extension from Redis
         ├─redis_queue.php                      Redis queue extension from Redis
         ├─redis_session.php                    Redis session extension from Redis
         ├─socket.php                           Socket extension
         ├─upload.php                           Upload extension
         └─...
          

## Reserved Words
  
CGI: c/cmd
CLI: c/cmd, d/data, p/pipe, t/time, r/ret
  
The words above are reserved by NervSys core. So that, they should be taken carefully when doing API calling.


## Config "setting.ini"

"setting.ini" locates right under "core" folder, which contains most of the important setting sections.

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
    
    
### INIT

    [INIT]
    This section works for all.
    This section holds system startup initial functions.
    
    Example & explain:
    
    Single setting:
    SomeDesc = dirA/dirB/model-func
    
    explain: 
    "\dirA\dirB\model::func()" will be called on startup without any agruments. 
    
    
    Multiple setting:
    DescA = dirA/dirB/model-func
    DescB[] = dirB/dirB/model-funcA
    DescB[] = dirB/dirB/model-funcB
    
    explain: 
    "\dirA\dirB\model::func()" & "\dirB\dirB\model::funcA()" & "\dirB\dirB\model::funcB()"
    will be called on startup without any agruments. 
    
    Notice: 
    The "Desc" key in "INIT" has no means for system, but for developers to know what they are for. 
    No agrument accepted and no returned value will be captured.

    
### LOAD

    [LOAD]
    This section works for all.
    This section holds "/subfolder/" startup initial functions.
    
    Example & explain:
    
    Single setting:
    dirA = dirB/model-func
    
    explain: 
    "\dirB\model::func($params)" will be called only once right before calling functions under dirA. 
    
    
    Multiple setting:
    dirA = dirX/model-func
    dirB[] = dirX/model-funcA
    dirB[] = dirX/model-funcB
    
    explain: 
    "\dirX\model::func($params)" will be called only once right before calling functions under dirA.
    "\dirX\model::funcA($params)" & "\dirX\model::funcB($params)" both will be called only once right 
    before calling functions under dirB.
    
    Notice: 
    The keys in "LOAD" section point to a subfolder, while the setting functions will be called 
    when the folder is being accessed. 
    Arguments are automatically accepted from API. No returned value will be captured.


### SIGNAL

    [SIGNAL]
    This section works for all.
    This section contains custom set observer signals and messages.
    
    Some example:
    
    1 = Process Terminated! Some reason...
    2 = Process Terminated! Some reason...
    3 = Process Terminated! Some reason...
    ...
    
    Once when observer received a signal not equal to 0, process terminated, corresponding message 
    will be logged and showed up.


### LOGGER

    [LOGGER]
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
    
    Don't delete these levels, just change the value to "off" or "0", if there is no need to log them.


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
        'test_a' => ['param_a', 'param_b'],
        
        'test_b' => [
                'pre' => ['DirA/classA-funcA', 'DirB/classA-funcA'],
                'post' => ['DirA/classB-funcA', 'DirB/classB-funcA'],
                'param' => ['param_c', 'param_d']
            ],
        
        'test_c' => [
                'param' => ['param_a', 'param_b', 'param_c']
            ]
    ];
    
    public static function init(): void 
    {
        //some code...
        //$tz controlling...
        //Unit data controlling...
        //etc...
    }
    
    public static function test_a(string $param_a, array $param_b): void
    {
        //some code...
    }
    
    public static function test_b(string $param_c, array $param_d): void
    {
        //some code...
    }
    
    public function test_c(string $param_a, array $param_b, string $param_c): void
    {
        //some code...
    }
}
```


## Keywords

### TrustZone

Every class which is exposed to API should always contain a static array variable named $tz. The content in $tz controls the exact method calling actions in the owner class.

Two types of $tz:

In $tz, the keys are function names which can be called by API. The contents are leading the actions. Functions that are not listed in $tz won't be called by API directly.

In the example above, $tz for function "test_a" is written in simple mode, while, $tz for function "test_b" is written in full mode. 

In simple mode, the contents are the MUST exist parameters for the function. API will ignore those functions when unit data structure is not matched $tz settings.

In full mode, MUST exist parameters are listing under 'param' key, they are doing the same thing. 'pre' key controls the pre-run methods, while 'post' key controls the post-run method. The two settings are executed before/after the function's calling.


### Autofill

Parameters in functions will be automatically filled by API once existing in unit data with the same name. Note that, this feature only works for API exposed functions. Once the function is being called, both $tz and params will be checked for qualification to pass.


### function "init"

"init" function is optional in API exposed classes. When API calling some functions in a class, it looks for "init" function first, and goes to call it when exists.


### Simple-Calling

As one of the basic support features, works good on web development.

Method: GET, POST
DataType: application/json, application/x-www-form-urlencoded, multipart/form-data

GET examples: 
api.php?cmd=DirA/ctr/TestA-test_a&param_a=xxx&param_b[]=yyy&param_b[]=zzz
api.php?cmd=DirA/ctr/TestA-test_b&param_c=xxx&param_d[]=yyy&param_d[]=zzz
api.php?cmd=DirA/ctr/TestA-test_c&param_a=xxx&param_c=xxx&param_b[]=yyy&param_b[]=zzz


### Multiple-Calling & Judging-Calling

As said, it is expected to process data like a nerve cell, Multiple-Calling & Judging-Calling MUST be supported.

Method: GET, POST
DataType: application/json, application/x-www-form-urlencoded, multipart/form-data

Let's suppose that, "TestB.php" in example has the functions and $tz with allowed parameters API needs below.


**Multiple-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz

Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz

Result: All REQUESTED functions that qualified both $tz & param checking will be called. All result data package will be put right under corresponding function name keys with class names as prefix. 


**Judging-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz

Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-DirA/TestB&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz

Result: All functions IN $tz will be looked over. Those ones which qualified both $tz & param checking will be called. All result data package will be put right under corresponding function name keys with class names as prefix. 


## Command Line

In this mode, PHP script will be called when the command and data matches the class & function structure. External programs listed in [CLI] section will also be called when command is qualified. Both results can be captured and compound under their key names.


### CLI options

    c/cmd: commands (separated by "-" when multiple)
    d/data: CGI data content
    p/pipe: CLI pipe data content
    t/time: read timeout (in microseconds; default "0" means read till done)
    r/ret: process return option (Available in CLI executable mode only)


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


## Credits

pdo_mysql Extension: [shawn](https://github.com/phpxiaowei)  
memcached Extension: [tggtzbh](https://github.com/tggtzbh)  


## Licensing

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the LICENSE.md file.