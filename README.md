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
    "\dirX\model::funcA($params)" & "\dirX\model::funcB($params)" will be called only once right before calling functions under dirB.
    
    Notice: 
    The keys in "LOAD" section point to a subfolder, while the setting functions will be called when the folder is being accessed. 
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
    
    Once when observer received a signal not equal to 0, process terminated, corresponding message will be logged and showed up.


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
    
    
    
    
    
    
    


## Example structures:

    root/                       **Root directory
        ├─PR_1/                 **Project 1 folder
        │     ├─ctr/            **Controller folder
        │     │    ├─a.php        a script
        │     │    └─b.php        b script
        │     ├─lib/            **library folder
        │     │    ├─a.php        a script
        │     │    └─b.php        b script
        │     ├─exe/            **executable program folder 
        │     │    ├─c.php        c script
        │     │    └─xxx          xxx executable program
        │     ├─.../            **Other folders containing functional scripts
        │     │    └─....php      Model ... script
        │     └─conf.php          Config file for Project 1
        └─PR_2/                 **Project 2 folder
              ├─model_a.php       Model a script
              ├─model_b.php       Model b script
              ├─model_c.php       Model c script
              ├─....php           Model ... script
              └─conf.php          Config file for Project 2

All script should under the right namespace for better calling by NervSys API. 


## Example:

    root/                       **Root directory
        └─pr_1/                 **Project 1 folder
              ├─ctr/            **Controller folder
              │    └─test_1.php   test 1 script
              ├─xxx/            **Controller folder
              │    └─xxx.php      test 1 script
              ├─test_2.php        test 2 script
              └─conf.php          Config file for Project 1


****Format for test_1.php:** 

    //The right namespace follows the path structure
    namespace pr_1\ctr;
        
    //Any other extensions and namespaces can be used here
    use ext\http;
    use core\ctr\router;
        
    //Class name should be exactly the same as the file name
    class test_1
    {
        /**
        * Important!!!
        *
        * This is the TrustZone config for NervSys API.
        * The keys should be function names which we want them to be called by API,
        * while the values should be the data MUST be sent to the function.
        * Don't put optional data in TrustZone values, 
        * otherwise, API will ignore the request if optional data is not passed.
        
        * All callable functions should be public.
        */
        public static $tz = [
            'test_a' => ['var_a', 'var_b', 'var_c'],
            'test_b' => ['var_b', 'var_c'],
            'test_c' => ['var_c'],
            
            //Leave empty but check via passing params
            //or, use them as above
            'test_d' => []
        ];
        
        /**
        * Initial function for API
        *
        * It will be called directly when exists.
        * Data, authority or more should be checked here before other functions
        * are called.
        *
        * It has the permission to modify the API TrustZone config in the class,
        * so, one or more keys can be added/removed when some cases are matched,
        * just to avoid some requests which are not permitted.
        *
        * Suggestion: Don't return here, unless it really needs a return.
        *
        * examples as follows
        */
        public static function init()
        {
            if (some case) {
                //Just add/remove one or more keys in TrustZone
                //but let other functions ready for calling
                self::$tz['func_name_ready_to_call'] = ['params'];
                unset(self::$tz['func_name_not_permitted']);
            } elseif (denied) {
                //Remove all from TrustZone
                self::$tz = [];
                //Give a return because no further functions will be be called
                return 'Sorry, you are not allowed to go any further!';
            }
            
            //More code
            //Data processing, function preparation, etc...
        }
        
        public static function test_a()
        {
            /**
            * This function must need variables [var_a, var_b, var_c]
            * We can fetch the data from router::$data['var_a'], router::$data['var_b'], ...
            * The returned value will be captured by router
            * stored in router::$result['namespace/class_name/function_name']
            */
            ... (Some code)
            return something;
        }
        
        public static function test_b()
        {
            /**
            * This function must need variables [var_b, var_c], variable [var_d] is optional
            * Just use router::$data['var_d'] if exists as it is optional
            */
            ... (Some code)
            return something;
        }
        
        /**
        * A Non-Static method
        */
        public function test_c()
        {
            /**
            * This function must need variable [var_c], variable [var_d] is optional
            * Just use router::$data['var_d'] if exists as it is optional
            */
            ... (Some code)
            return something;
        }
        
        /**
        * Params passing example
        */
        public function test_d(int $var_a, string $var_b, $var_c = 1, $var_d = [])
        {
            /**
            * TrustZone config for this method is empty, but,
            * there are params which will be checked and passed from API.
            *
            * The names of the params are equal to the data in router::$data.
            * All params will be converted to the right type if declared, or, 
            * kept in original type as input data type if not declared.
            *
            * We can request without optional params if no need to pass, then, 
            * the default values will be used instead.
            *
            * We don't need to build the data manually, both value and order.
            * The original data in router::$data will not change.
            * 
            * URL GET EXAMPLE (Please read detail examples below):
            * http://HostName/api.php?cmd=pr_1/ctr/test_1-test_d&var_a=1&var_b=b&var_c=10
            * 
            * NOTE: Just write functions as usual.
            */
            ... (Some code)
            return something;
        }
    }


****Format for test_2.php:**

    //The right namespace follows the path structure
    namespace pr_1;
        
    //Any other extensions and namespaces can be used here
    use ext\sock;
    use core\ctr\router;
        
    //Class name shoule be exactly the same as the file name
    class test_2
    {
        public static $tz = [
            'test_a' => ['var_a', 'var_b', 'var_c'],
            'test_b' => ['var_b', 'var_c'],
            'test_c' => []
        ];
        
        public static function test_a()
        {
            //Fetch variables
            ... (Some code)
            return something;
        }
        
        public static function test_b()
        {
            //Fetch variables
            ... (Some code)
            return something;
        }
        
        public static function test_c()
        {
            //Fetch optional variables
            ... (Some code)
            return something;
        }
    }


As said, it is an universal API controller. So, we can easily use it as follows as usual.  
It receives normal GET/POST data, and stream data in JSON format.  
Remember one param named "c" or "cmd", the two are equal and both reserved by NervSys core.  


**Examples (using GET):**

    Usage:
        
    for test_1.php
        
    1. http://HostName/api.php?c=pr_1/ctr/test_1-test_a&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_1-test_a&var_a=a&var_b=b&var_c=c
    3. ...
        
    Above are the strict mode with detailed function name, only "test_a" is called.
        
    Let's see more:
        
    1. http://HostName/api.php?c=pr_1/ctr/test_2-test_b&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_2-test_b&var_a=a&var_b=b&var_c=c
    3. ...
        
    We called "test_b" in "pr_1/ctr/test_2" with params "var_b" and "var_c", 
    "var_a" is obviously usless and ignore.
        
    And there goes some interesting things, what if we do as follows?
        
    1. http://HostName/api.php?c=pr_1/ctr/test_1-test_a-test_b&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_1-test_a-test_b&var_a=a&var_b=b&var_c=c
    3. ...
        
    Both "test_a" and "test_b" in "pr_1/ctr/test_1" will be called 
    sharing the same data of "var_b" and "var_c", "test_a" used one more "var_a".
        
    This time, we do it as:
        
    http://HostName/api.php?cmd=pr_1/ctr/test_2-test_a-test_b-test_c&var_a=a&var_b=b&var_c=c
        
    "test_c" will run right after, as it needs no required variables.
    We now can get some compound results with differences in keys.
        
    And what if we do as follows?
        
    1. http://HostName/api.php?c=pr_1/ctr/test_1&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_1&var_a=a&var_b=b&var_c=c
    3. ...
        
    Could it be an error calling?
        
    This is called loose mode. 
    If we do this, all functions listed in TrustZone 
    will be checked with the input data structure, 
    and will be called if the structure matched or contained.
    "test_c" will always run right after, since it needs no required variables.
        
    Call order is the TrustZone list order.
        
    So, it'll be very useful to calculate in multiple algorithms 
    with one same data pack. 
        
    And more things here, we can always refresh the data structure 
    adding new data from results in our own function codes, 
    and let others be called in midway.
        
    This is more powerful than strict mode, 
    but may bring some harm if don't pay attantion on it, 
    espcially on data written. Functions will be called out of prediction.
        
    Once when we call as follows:
            
    1. http://HostName/api.php?c=pr_1/ctr/test_2-test_a&var_a=a&var_b=b
    2. http://HostName/api.php?cmd=pr_1/ctr/test_2-test_a&var_a=a&var_c=c
    3. http://HostName/api.php?cmd=pr_1/ctr/test_2-test_a&var_a=a&var_c=c&d=d&xxx=xxx...
    4. http://HostName/api.php?cmd=pr_1/ctr/test_2-test_a&whatever...(but missed some of "var_a", "var_b", "var_c")
        
    This won't happen because the input data structure dismatched.
    API just chooses to ignore the request to "test_a" function,
    and gives us a notice "TrustZone missing [what]" when "DEBUG" is set.
        
    And what's more:
        
    loose style:
    1. http://HostName/api.php?c=pr_1/ctr/test_1-pr_1/test_2&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_1-pr_1/test_2&var_a=a&var_b=b&var_c=c
        
    All functions that match the input data strucuture in both "pr_1/ctr/test_1" and "pr_1/test_2"
    will run. With this, we can call multiple functions in multiple modules right in one request.
    These functions share the same source data, and do their own work.
        
    strict style:
    1. http://HostName/api.php?c=pr_1/ctr/test_1-pr_1/test_2-test_a&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=pr_1/ctr/test_1-pr_1/test_2-test_a-test_b&var_a=a&var_b=b&var_c=c
        
    Functions placed in the URL (in "c"/"cmd" value, separated by "-", order ignored, same in "POST") 
    and match the input data strucuture at the same time in both "pr_1/ctr/test_1" and "pr_1/test_2"
    will run. With this, we can call EXACT multiple functions in EXACT multiple modules in one request.
    These modules share the same function names when exist. 
    All functions share the same source data and run with the input order.
        
    If we want to hide the real request path, make sure the "c" or "cmd" key is listing in "config.ini"
    under [CGI] section with the key as the input "c" or "cmd", and the real path and its possible
    params as the value, or ever more. Settings can be compound.
        
    Some examples:
        
    "config.ini"
        
    [CGI]
    mycmd_1 = "pr_1/ctr/test_1"
    mycmd_2 = "pr_1/test_2-test_a"
    mycmd_3 = "pr_1/test_2-test_a-test_b"
    ...
        
    Then, the following two requests are equal.
        
    1. http://HostName/api.php?cmd=pr_1/ctr/test_1-pr_1/test_2-test_a-test_b&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?cmd=mycmd_1-mycmd_3&var_a=a&var_b=b&var_c=c


**CLI Command usage:**

    CLI options are as follows:
        
    c/cmd: commands (separated by "-" when multiple)
    d/data: CGI data content
    p/pipe: CLI pipe data content
    r/ret: process return option (Available in CLI executable mode only)
    l/log: process log option (cmd, argv, pipe, error, result. Available in CLI executable mode only)
    t/time: read time (in microseconds; default "0" means read till done. Works when r/ret or l/log is set)
        
    **Examples:
        
    Let's take "pr_1/ctr/test_1" as an example.
    Full command should be as some type of follows:
        
    1. /path/php api.php --ret --cmd "pr_1/ctr/test_1-test_a" --data "var_a=a&var_b=b&var_c=c"
    2. /path/php api.php -r -t 10000 -c "pr_1/ctr/test_1-test_b" -d "var_b=b&var_c=c"
    3. /path/php api.php -r -l -c "pr_1/ctr/test_1-test_a-test_b" -d "var_a=a&var_b=b&var_c=c"
    4. /path/php api.php --ret --cmd "pr_1/ctr/test_1-test_a-test_b" --data "var_a=a&var_b=b&var_c=c"
    5. ...
        
    JSON data package is also support as CGI mode
        
    We can also do as follows:
        
    1. /path/php api.php -d "var_a=a&var_b=b&var_c=c" pr_1/ctr/test_1-test_a
    2. /path/php api.php -d "var_b=b&var_c=c" pr_1/ctr/test_1-test_b
    3. /path/php api.php -d "var_a=a&var_b=b&var_c=c" pr_1/ctr/test_1-test_a-test_b
    4. /path/php api.php -d "var_a=a&var_b=b&var_c=c" pr_1/ctr/test_1
    5. ...
    
    
    **CLI executable mode**
        
    If we need to call external programs, make sure the "c" or "cmd" key is listing in "config.ini"
    under [CLI] section with the executable path and its possible params as the value, or ever more.
        
    Some examples:
        
    "config.ini"
        
    [CLI]
    mycmd = "/xxx/path/mycmd -c -d --more"
        
    Command:
        
    1. /path/php api.php mycmd
    2. /path/php api.php -c mycmd a b c
    3. /path/php api.php --cmd mycmd -v a -b b c
    4. ...
        
    If data needs to be sent via pipe:
        
    1. /path/php api.php mycmd -p "some data"
    2. /path/php api.php -c mycmd --pipe "some data"
    3. ...
        
    Output data will be also captured as in CGI mode and outputs via STDOUT.
        
    Don't forget to use "-r" or "--ret" to capture output data.
    If time is too short to run extenal programs, use "-t ms" or "--time ms"
        
    In CLI mode, there is a globle variable named "PHP",
    with its value pointing to the running php executable path,
    which we can execute another PHP process at anytime when needed.
        
    Something we can do as follows:
    /path/php /path/api.php -r PHP -v
    /path/php /path/api.php -r PHP /path/api.php -r demo/demo
        
    "PHP" value can be fetched in "os::get_env()".
        
    
**Multiple commands containing both CGI and CLI**
    
    Only works under CLI executable mode.
    Simple Example:
    
    /path/php /path/api.php -r -d "var_a=a&var_b=b&var_c=c" pr_1/ctr/test_1-PHP -v
        
    
**Chain Loading Example:**

    //The right namespace follows the path structure
    namespace pr_1\ctr;
        
    //Any other extensions and namespaces can be used here
    use ext\crypt;
    use core\ctr\router;
        
    //Class name should be exactly the same as the file name
    class test_1
    {
        public static $tz = [
            'test_a' => ['var_a', 'var_b', 'var_c'],
            'test_b' => ['var_d', 'var_e'],
            'test_c' => ['var_f'],
            'test_d' => []
        ];
        
        public static function test_a()
        {
            if (in case){
                router::$data['var_d'] = 'something';
                router::$data['var_e'] = 'something';
            }
            
            return something;
        }
        
        public static function test_b()
        {
            if (in case){
                router::$data['var_f'] = 'something';
            }
            
            return something;
        }
        
        /**
        * A Non-Static method
        */
        public function test_c()
        {
            if (in case){
                router::$data['var_g'] = 'something';
            }
            
            return something;
        }
        
        /**
        * Params passing example
        */
        public function test_d(string $var_g)
        {
            ... (Some code)
            return something;
        }
    }

      
    We can simple give data for "var_a", "var_b" and "var_c" to make them all called.
    
    1. http://HostName/api.php?c=pr_1/ctr/test_1&var_a=a&var_b=b&var_c=c
    2. http://HostName/api.php?c=pr_1/ctr/test_1-test_a-test_b-test_c-test_d&var_a=a&var_b=b&var_c=c


## Tests & Demos

Version 5.2.0 is on going, and not compatible with versions before.  
Test scripts for Ver 5.0.0 is here: [**TESTS**](https://github.com/NervSys/NS-Tests).  
Demos for Ver 5.0.0 is here: [DEMO](https://github.com/Jerry-Shaw/NS-Demo). Just get it a try. 


## Credits

pdo_mysql Extension: [shawn](https://github.com/phpxiaowei)  
memcached Extension: [tggtzbh](https://github.com/tggtzbh)  
README Chinese Translation: [MileHan](https://github.com/MileHan), [kristemZZ](https://github.com/kristemZZ), [JreSun](https://github.com/JRE-Sun). URL: [中文文档](https://github.com/Jerry-Shaw/NervSys/blob/master/README_zh-CN.md) 


## Old Version:

Old version before 3.0.0 is discontinued and the source codes located here: [3.2.0](https://github.com/Jerry-Shaw/NervSys/tree/3.2)


## Licensing

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the LICENSE.md file.