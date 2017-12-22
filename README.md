**Nervsys** is a very slight framework based on PHP7.1+ for universal API controlling. 

It can be used as:

    1. Normal develop framework for Web
    2. API controller for all types of Apps
    3. Client for program communication
    4. Or more...

As normally use, it responses one result from one method to one request, just like what we do now on an ordinary web development. But, it may response multiple results from multiple methods to one request, when we need it to "guess" what we need based on the data we gave.

Don't expect too much, it is just a newborn framework though~ 

Extensions in "/ext/" makes it greater than we can image. 

We need you. Ideas, actions, participation, and support.

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

============================================================================================================

    **Format for test_1.php:
        
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
        * The keys should be the function names which you want them to be visited by NervSys API,
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
            //this function must need variables [a, b, c]
            //You can fetch the data from router::$data, like router::$data['a'], router::$data['b'], ...
            ... (your code)
            //The returned value will be captured by router stored in router::$result['namespace/class_name/function_name']
            return something;
        }
        
        public static function test_b()
        {
            //this function must need variables [b, c], variable [d] can be optional
            //Just use router::$data['d'] if exists as it is optional
            ... (your code)
            return something;
        }
    }

============================================================================================================

    **Format for test_2.php:
        
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
            ... (your code)
            return something;
        }
        
        public static function test_b()
        {
            //Fetch variables
            ... (your code)
            return something;
        }
    }

============================================================================================================

As said, it is an universal API controller. So, we can easily use it as follows as usual.

It receives normal GET/POST data, and stream data in JSON format.

Remember one param named "c" or "cmd", the two are equal.

**Example using GET:**

    Usage:
        
    for test_1.php
        
    1. http://yourhost/api.php&c=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    2. http://yourhost/api.php&cmd=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    
    Above are the strict mode with detailed function name, only "test_a" will be called.
        
    Let's see more:
        
    1. http://yourhost/api.php&c=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
    2. http://yourhost/api.php&cmd=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
        
    We called "test_b" in "pr_1\ctr\test_2" with params "b" and "c", "a" is obviously usless and ignore.
        
    And there goes some interesting things, what if we do as follows?
        
    1. http://yourhost/api.php&c=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    2. http://yourhost/api.php&cmd=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    
    ...
        
    Right, both "test_a" and "test_b" in "pr_1\ctr\test_1" will be called sharing the same data of "b" and "c", "test_a" used one more "a".
        
    You now can get some compound results with difference keys.
        
    And what if we do as follows?
        
    1. http://yourhost/api.php&c=pr_1\ctr\test_1&a=a&b=b&c=c
    2. http://yourhost/api.php&cmd=pr_1\ctr\test_1&a=a&b=b&c=c
        
    Will it stucked?
        
    This is called loose mode. If we do this, all functions listed in Safe Key will be checked with the input data structure and will be called if the structure matched or contained. So, it'll be very useful to calculate in multiple algorithms with one same data pack. And more things here, you can always refresh the data structure adding new data from results in you own function codes, and let others be called in midway.
        
    This is more powerful than strict mode, but may bring some harm if don't pay attantion on it, espcially on data written. Functions will be called out of your prediction.

============================================================================================================

Notice: once if there is only one element in router's result, it will ouput the inner content value in JSON and ignore the key('namespace/class_name/function_name'). If you set "DEBUG" to true and run it under cgi, the results should be complex because 3 more elements for debugging will be added to results as well. Always remember to close "DEBUG" option when all are under production environment, or, the result structure will confuse you with 3 more inside.

Requirements: PHP7.1+ and above. Any kind of web server or running under CLI mode. MySQL, Redis and redis extension for PHP depend on yourself.

Version 5.0.0 is on going, and not compatible with versions before.

Demos for Ver 5.0.0 is here: https://github.com/Jerry-Shaw/demo. You can try it in NervSys with detailed comments in the scripts.

Functional extensions (class) are considered to moved out to the third part to maintain. Not only extensions, but sub-projects based on NervSys are expected. Everyone can join the project. Ideas, codes, suggests, supports, etc... And many thanks!

Old version before 3.0.0 is discontinued and the source codes located here: https://github.com/Jerry-Shaw/NervSys/tree/3.2
