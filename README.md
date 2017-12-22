**Nervsys** is a very slight framework based on PHP7.1+ for universal API controlling. 

It can be used as:

    1. Normal develop framework for Web
    2. API controller for all types of Apps
    3. Client for program communication
    4. Or more...


As normally use, it responses one result from one method to one request, just like what we do now on an ordinary web development. But, it may response multiple results from multiple methods to one request, when we need it to "guess" what we need based on the data we gave.

Don't expect too much, it is just a newborn framework though~ 

Extensions in "/ext/" makes it greater than we can image. 

We need you. Ideas, actions, participation, and supports.


**Structure Introduce:**
        
      /                                 **Root directory
      ├─api.php                         Main entry
      ├─README.md                       Readme
      ├─LICENSE                         Lincese
      ├─core/                           **Core directory
      │     ├─cli/                      **CLI working directory
      │     │    └─logs/                **CLI logging directory
      │     ├─ctr/                      **Controller directory
      │     │    ├─os/                  **OS controller directory
      │     │    │   ├─linux.php        Linux Controller
      │     │    │   ├─winnt.php        WinNT Controller
      │     │    │   └─(need more...)   Need more controllers
      │     │    ├─router/              **Router directory
      │     │    │       ├─cgi.php      CGI execution script
      │     │    │       └─cli.php      CLI execution script
      │     │    ├─os.php               Main OS controller
      │     │    └─router.php           Main Router controller
      │     ├─cfg.ini                   Config file for CLI executable command
      │     └─cfg.php                   Config file for core system
      └─ext/                            **extension directory
           ├─lib/                       **extension interface directory
           │    └─keys.php              Cryption Key generator interface
           ├─upload/                    **Upload extension relaterd directory
           │       ├─en-US/             **Upload language folder (en-US)
           │       ├─zh-CN/             **Upload language folder (zh-CN)
           │       └─upload.ini         Upload error code file
           ├─crypt.php                  Encrypt/decrypt extension
           ├─errno.php                  Error code extension
           ├─file.php                   Filesystem related IO extension
           ├─http.php                   HTTP request extension
           ├─image.php                  Image processing extension
           ├─keygen.php                 Cryption Key generator extension
           ├─lang.php                   Language extension
           ├─pdo.php                    PDO connector extension
           ├─pdo_mysql.php              MySQL extension for PDO
           ├─redis.php                  Redis connector extension
           ├─redis_session.php          Session extension for Redis
           ├─sock.php                   Socket extension
           └─upload.php                 Upload extension


Files of a project should be better containing just in one folder right under the ROOT folder. Files inside project can be placed as what developers like. Some example structures as follows:


    root/                       **Root directory
        ├─PR_1/                 **Project 1
        │     ├─ctr/            **Controller folder
        │     ├─lib/            **library folder
        │     ├─exe/            **executable program folder 
        │     └─.../            **Other folders containing functional scripts
        └─PR_2/                 **Project 2
              ├─model_a.php     Model a script
              ├─model_b.php     Model b script
              ├─model_c.php     Model c script
              └─....php         Model ... script
    
    
All script should under the right namespace for better calling by NervSys API.


**Example:**

    root/                       **Root directory
        └─pr_1/                 **Project 1
              ├─ctr/            **Controller folder
              │    └─test_1.php   test 1 script
              └─test_2.php        test 2 script

****Format for test_1.php:**
        
    //The right namespace follows the path structure
    namespace pr_1\ctr;
        
    //Any other extensions and namespaces can be used here
    use ext\http;
        
    //Class name shoule be exactly the same as the file name
    class test_1
    {
        /**
        * Important!!!
        * 
        * This is the Safe Key area for NervSys API.
        * The keys should be the function names which we want them to be visited by API,
        * while the values should be the data MUST be send into the function.
        * Don't write optional data in Safe Key values, 
        * or, the request will be ignored if the optional data is not passed.
        * All visitable functions should be public and static.
        */
        public static $key = [
            test_a = [a, b, c],
            test_b = [b, c]
        ];
        
        public static function test_a()
        {
            /**
            * This function must need variables [a, b, c]
            * We can fetch the data from router::$data['a'], router::$data['b'], ...
            * The returned value will be captured by router
            * stored in router::$result['namespace/class_name/function_name']
            */
            ... (Some code)
            return something;
        }
        
        public static function test_b()
        {
            /**
            * This function must need variables [b, c], variable [d] is optional
            * Just use router::$data['d'] if exists as it is optional
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
        
    //Class name shoule be exactly the same as the file name
    class test_2
    {
        public static $key = [
            test_a = [a, b, c],
            test_b = [b, c]
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
    }


As said, it is an universal API controller. So, we can easily use it as follows as usual.

It receives normal GET/POST data, and stream data in JSON format.

Remember one param named "c" or "cmd", the two are equal.


**Example using GET:**

    Usage:
        
    for test_1.php
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    3. ...
    
    Above are the strict mode with detailed function name, 
    only "test_a" will be called.
        
    Let's see more:
        
    1. http://HostName/api.php&c=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
    3. ...
        
    We called "test_b" in "pr_1\ctr\test_2" with params "b" and "c", 
    "a" is obviously usless and ignore.
        
    And there goes some interesting things, what if we do as follows?
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    3. ...
        
    Right, both "test_a" and "test_b" in "pr_1\ctr\test_1" will be called 
    sharing the same data of "b" and "c", "test_a" used one more "a".
        
    We now can get some compound results with difference keys.
        
    And what if we do as follows?
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1&a=a&b=b&c=c
    3. ...
        
    Could it be an error calling?
        
    This is called loose mode. 
    If we do this, all functions listed in Safe Key 
    will be checked with the input data structure, 
    and will be called if the structure matched or contained. 
        
    Call order is the Safe Key list order.
        
    So, it'll be very useful to calculate in multiple algorithms 
    with one same data pack. 
        
    And more things here, we can always refresh the data structure 
    adding new data from results in our own function codes, 
    and let others be called in midway.
        
    This is more powerful than strict mode, 
    but may bring some harm if don't pay attantion on it, 
    espcially on data written. Functions will be called out of prediction.
    
**CLI Command usage:**
    
    CLI options are as follows:
        
    c/cmd: command
    d/data: CGI data content
    p/pipe: CLI pipe content
    r/return: return type (result (default) / error / data / cmd, multiple options)
    t/timeout: timeout for return (in microseconds, default value is 5000ms when r/return is set)
    l/log: log option
        
    **Examples:
        
    Let's take "pr_1\ctr\test_1" as an example.
        
    Full command should be as some type of dollows:
        
    1. /path/php api.php --return result/data/error --cmd "pr_1\ctr\test_1-test_a" --data "a=a&b=b&c=c"
    2. /path/php api.php -r result/data -t 10000 -c "pr_1\ctr\test_1-test_b" -d "b=b&c=c"
    3. /path/php api.php -r result -l -c "pr_1\ctr\test_1-test_a-test_b" -d "a=a&b=b&c=c"
    4. /path/php api.php --return result --cmd "pr_1\ctr\test_1-test_a-test_b" --data "a=a&b=b&c=c"
    5. ...
        
    JSON data package is also support as CGI mode
        
    We can also do as follows:
        
    1. /path/php api.php pr_1\ctr\test_1-test_a -d "a=a&b=b&c=c"
    2. /path/php api.php pr_1\ctr\test_1-test_b -d "b=b&c=c"
    3. /path/php api.php pr_1\ctr\test_1-test_a-test_b -d "a=a&b=b&c=c"
    4. /path/php api.php pr_1\ctr\test_1 -d "a=a&b=b&c=c"
    5. ...
        
    If we need call extenal programs, make sure the "c" or "cmd" name is listing in "cfg.ini" 
    with the executable path as the value
        
    Something examples:
        
    "cfg.ini"
        
    mycmd = "/xxx/path/mycmd"
        
    Command:
        
    1. /path/php api.php mycmd
    2. /path/php api.php -c mycmd a b c
    3. /path/php api.php --cmd mycmd -v a -b b c
    4. ...
        
    If data should be send via pipes:
        
    1. /path/php api.php mycmd -p "some data"
    2. /path/php api.php -c mycmd --pipe "some data"
    3. ...
        
    Output data will be also captured as in CGI mode and outputs via STDOUT.
        
    Don't forget to use "-r result" or "--return result" to capture output result.
    If the time is too short to run extenal programs, use "-t ms" or "-timeout ms"
    

Notice: once if there is only one element in router's result, it will ouput the inner content value in JSON and ignore the key('namespace/class_name/function_name'). If "DEBUG" option is set to true and run it under cgi, the results should be complex because 3 more elements for debugging will be added to results as well. Always remember to close "DEBUG" option when all are under production environment, or, the result structure will confuse us with 3 more values inside.

Requirements: PHP7.1+ and above. Any kind of web server or running under CLI mode.

Version 5.0.0 is on going, and not compatible with versions before.

Demos for Ver 5.0.0 is here: https://github.com/Jerry-Shaw/demo. Just can try it in NervSys with detailed comments in the scripts.

Functional extensions (class) are considered to moved out to the third part to maintain. Not only extensions, but sub-projects based on NervSys are expected. Everyone can join the project. Ideas, codes, suggests, supports, etc... And many thanks!

Old version before 3.0.0 is discontinued and the source codes located here: https://github.com/Jerry-Shaw/NervSys/tree/3.2