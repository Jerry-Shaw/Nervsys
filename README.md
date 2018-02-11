# Nervsys

A very slight framework based on PHP7.1+ for universal API controlling.  
基于PHP7.1+的轻量级框架**Nervsys** for universal API controlling. 

Requirements: PHP7.1+ and above. Any kind of web server or running under CLI mode.  
任何一种Web服务器或者PHP的命令行模式

It can be used as:

    1. Normal develop framework for Web
    2. API controller for all types of Apps
    3. Client for program communication
    4. Or more...
    
它可以作为：

    1.通用的Web开发框架
    2.编写各类App接口
    3.通信客户端
    4.更多......
    
As normally use, it responses one result from one method to one request, just like what we do now on an ordinary web development. 
通常情况下，一个请求调用一个方法会返回一个结果，就像我们现在在一般的Web项目开发中做的那样。
But, it may response multiple results from multiple methods to one request, when we need it to "guess" what we need based on the data we gave.  
但是，当我们需要它根据我们给的数据“猜”出结果时，它可以从一个请求调用多个方法返回多种结果。

Don't expect too much, it is just a newborn framework though~ 

Extensions in "/ext/" makes it greater than we can image.  
在"/ext/"下的扩展让它变得比我们想的更加强大。

Functional extensions (class) are considered to moved out to the third part to maintain. Not only extensions, but sub-projects based on NervSys are expected.  
功能性的扩展（类）打算移到第三部分来维护。不仅仅是扩展，基于神经元的子项目也应该有。  
Everyone can join the project. Ideas, codes, tests, suggests, supports, etc... And many thanks!  
每个人都可以加入这个项目，提供想法，代码，测试，建议等等。非常感谢！

## Structure:
**结构说明:**       
        
      /                                 **Root directory
      ├─api.php                           Main entry
      ├─README.md                         Readme
      ├─LICENSE                           Lincese
      ├─core/                           **Core directory                            核心目录
      │     ├─cli/                      **CLI working directory
      │     │    └─logs/                **CLI logging directory
      │     ├─ctr/                      **Controller directory
      │     │    ├─os/                  **OS controller directory
      │     │    │   ├─linux.php          Linux Controller
      │     │    │   ├─winnt.php          WinNT Controller
      │     │    │   └─(need more...)     Need more controllers
      │     │    ├─router/              **Router directory
      │     │    │       ├─cgi.php        CGI execution script                      CGI执行脚本 
      │     │    │       └─cli.php        CLI execution script                      CLI执行脚本
      │     │    ├─os.php                 Main OS controller                        操作系统主控制器
      │     │    └─router.php             Main Router controller                    路由主控制器
      │     ├─cfg.ini                     Config file for CLI executable command    CLI可执行命令的配置文件
      │     └─cfg.php                     Config file for core system               核心系统的配置文件
      └─ext/                            **extension directory                       扩展目录
           ├─font/                      **font directory
           ├─lib/                       **extension interface directory             扩展接口目录
           │    └─keys.php                Cryption Key generator interface          密钥生成接口
           ├─upload/                    **Upload extension related directory        扩展关联目录
           │       ├─en-US/             **Upload language folder (en-US)
           │       ├─zh-CN/             **Upload language folder (zh-CN)
           │       └─upload.ini           Upload error code file
           ├─authcode.php                 Auth Code extension
           ├─crypt.php                    Encrypt/decrypt extension                 加密/解密扩展
           ├─errno.php                    Error code extension                      错误代码扩展
           ├─file.php                     Filesystem related IO extension           文件系统关联输入输出的扩展
           ├─http.php                     HTTP request extension                    HTTP请求扩展
           ├─image.php                    Image processing extension                图像处理扩展
           ├─keygen.php                   Cryption Key generator extension          密钥生成扩展
           ├─lang.php                     Language extension                        语言扩展
           ├─mpc.php                      Multi-Process Controller Extension
           ├─pdo.php                      PDO connector extension                   PDO连接器扩展
           ├─pdo_mysql.php                MySQL extension for PDO                   pdo_mysql扩展
           ├─redis.php                    Redis connector extension                 Redis连接器扩展
           ├─redis_lock.php               Lock extension on Redis
           ├─redis_queue.php              Queue extension on Redis
           ├─redis_session.php            Session extension on Redis                redis_session扩展
           ├─sock.php                     Socket extension                          Socket扩展
           ├─upload.php                   Upload extension                          上传扩展
           └─...                          There will be more in the near future     近期会有更有用的扩展更新。

Files of a project should be better containing just in one folder right under the ROOT folder.  
项目文件最好放在根目录下的一个文件夹里。  
Files inside a project can be placed as will.  
项目中的文件可以按如下方式放置：

Some example structures:

结构示例：

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
        │     └─cfg.php           Config file for Project 1
        └─PR_2/                 **Project 2 folder
              ├─model_a.php       Model a script
              ├─model_b.php       Model b script
              ├─model_c.php       Model c script
              ├─....php           Model ... script
              └─cfg.php           Config file for Project 2
    
    
All script should under the right namespace for better calling by NervSys API.  
所有的脚本应该在正确的命名空间里，以便NervSys API调用。

## Example:

**示例：**

    root/                       **Root directory
        └─pr_1/                 **Project 1 folder
              ├─ctr/            **Controller folder
              │    └─test_1.php   test 1 script
              ├─xxx/            **Controller folder
              │    └─xxx.php      test 1 script
              ├─test_2.php        test 2 script
              └─cfg.php           Config file for Project 1


****Format for test_1.php:** 

****test_1.php 模板格式**
        
    //The right namespace follows the path structure
    //根据脚本路径定义命名空间
    namespace pr_1\ctr;
        
    //Any other extensions and namespaces can be used here
    //use 其他的扩展和命名空间
    use ext\http;
        
    //Class name should be exactly the same as the file name
    //类名保持和文件名一致  
    class test_1
    {
        /**
        * Important!!!
        * 重要！！！ 
        *
        * This is the Safe Key config for NervSys API.
        * The keys should be function names which we want them to be called by API,
        * while the values should be the data MUST be sent to the function.
        * Don't put optional data in Safe Key values, 
        * otherwise, API will ignore the request if optional data is not passed.
        
        * All callable functions should be public.
        *
        * 这是NervSys API的安全键区。
        * 只有我们希望通过API访问到的方法名称能够作为键名，
        * 而键值是发送到这个方法的数据。
        * 不要在安全键值里写进可选数据，或者当可选数据未选中的时候，这个请求会被忽略
        
        * 所有可访问的方法都是public（公有）的
        */
        public static $key = [
            test_a = [a, b, c],
            test_b = [b, c],
            test_c = [c]
        ];
        
        public static function test_a()
        {
            /**
            * This function must need variables [a, b, c]
            * We can fetch the data from router::$data['a'], router::$data['b'], ...
            * The returned value will be captured by router
            * stored in router::$result['namespace/class_name/function_name']
            *
            * 这个函数必须传入参数a, b, c
            * 我们可以从router::$data['a'], router::$data['b']...获取到这些数据
            * 返回值会被保存在router::$result['namespace/class_name/function_name']
            */
            ... (Some code)
            return something;
        }
        
        public static function test_b()
        {
            /**
            * This function must need variables [b, c], variable [d] is optional
            * Just use router::$data['d'] if exists as it is optional
            *
            * 这个函数必须参数b, c，变量d是参选参数
            * 如果参数d被传入的话就用router::$data['d']获取
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
            * This function must need variable [c], variable [d] is optional
            * Just use router::$data['d'] if exists as it is optional
            *
            * 这个函数必须参数c，变量d是参选参数
            * 如果参数d被传入的话就用router::$data['d']获取
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
            test_b = [b, c],
            test_c = []
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
之前说过，它是一个通用的API控制器。所以我们可以和平常一样轻松的使用它。  
It receives normal GET/POST data, and stream data in JSON format.  
它能够接收GET/POST数据，和JSON格式的数据流。  
Remember one param named "c" or "cmd", the two are equal.  
记住：参数“c”或者参数"cmd"，这两者是等价的。  

**Examples (using GET):**

    Usage:
        
    for test_1.php
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-test_a&a=a&b=b&c=c
    3. ...
        
    Above are the strict mode with detailed function name, only "test_a" is called.
    上述是使用的具体方法名的严格模式，只有"test_a"方法被调用
        
    Let's see more:
    让我们看看更多
        
    1. http://HostName/api.php&c=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_2-test_b&a=a&b=b&c=c
    3. ...
        
    We called "test_b" in "pr_1\ctr\test_2" with params "b" and "c", 
    "a" is obviously usless and ignore.
    我们在"pr_1\ctr\test_2"中调用了"test_b"方法，传入参数"b","c",而变量“a”明显是没用的，会被忽略掉。
        
    And there goes some interesting things, what if we do as follows?
    当我们做出如下操作的时候，会发生一些有趣的事情。
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-test_a-test_b&a=a&b=b&c=c
    3. ...
        
    Right, both "test_a" and "test_b" in "pr_1\ctr\test_1" will be called 
    sharing the same data of "b" and "c", "test_a" used one more "a".
    是的，在"pr_1\ctr\test_1"中的"test_a"和"test_b"方法都会被调用，而且使用相同的参数"b","c"。"test_a"多使用一个参数"a".
        
    This time, we do it as:
    这次，我们这样做：
        
    http://HostName/api.php&cmd=pr_1\ctr\test_2-test_a-test_b-test_c&a=a&b=b&c=c
        
    Yep. "test_c" will run right after, as it needs no required variables.
    We now can get some compound results with differences in keys.
    对的，方法"test_c"会在稍后执行，因为它不需要任何参数。
    我们现在可以通过键的不同获取到一些复合的结果集。    
        
    And what if we do as follows?
    那如果我们这样做会怎样呢？
        
    1. http://HostName/api.php&c=pr_1\ctr\test_1&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1&a=a&b=b&c=c
    3. ...
        
    Could it be an error calling?
    它是一种错误的调用吗？
        
    This is called loose mode. 
    If we do this, all functions listed in Safe Key 
    will be checked with the input data structure, 
    and will be called if the structure matched or contained.
    "test_c" will always run right after, since it needs no required variables.
    这是一种宽松调用模式。
    如果这样调用的话，在Safe Key中列出的方法都会被传入的数据结构核对一遍，如果结构匹配或者包含，则该方法会被调用。
    方法"test_c"将始终在之后执行，因为它不需要任何参数。
        
    Call order is the Safe Key list order.
    调用顺序即Safe Key中列出的顺序。
        
    So, it'll be very useful to calculate in multiple algorithms 
    with one same data pack. 
    所以，在使用同一个数据包进行多算法计算时，NervSys API是非常有用的。
        
    And more things here, we can always refresh the data structure 
    adding new data from results in our own function codes, 
    and let others be called in midway.
    我们可以一直从自己的函数代码中添加数据，更新数据结构，让其他方法在中途被调用。
        
    This is more powerful than strict mode, 
    but may bring some harm if don't pay attantion on it, 
    espcially on data written. Functions will be called out of prediction.
    这比严格模式更有力，但是不注意的话也会带来一些缺陷。
    尤其是数据写入时，调用的方法会和预期的不一致。    
        
    Once when we call as follows:
    当我们这样做时：
            
    1. http://HostName/api.php&c=pr_1\ctr\test_2-test_a&a=a&b=b
    2. http://HostName/api.php&cmd=pr_1\ctr\test_2-test_a&a=a&c=c
    3. http://HostName/api.php&cmd=pr_1\ctr\test_2-test_a&a=a&c=c&d=d&xxx=xxx...
    4. http://HostName/api.php&cmd=pr_1\ctr\test_2-test_a&whatever...(but missed some of "a", "b", "c")
        
    This won't happen because the input data structure dismatched.
    API just chooses to ignore the request to "test_a" function,
    and gives us a notice "[what] is missing" when "DEBUG" is set.
    因为输入的数据结构不匹配，所以这不可能发生。
    API会选择忽略调用"test_a"方法的请求，如果"DEBUG"有设置的话，还会通知我们"[what] is missing"。
        
    And what's more:
        
    loose style:
    宽松模式：
    1. http://HostName/api.php&c=pr_1\ctr\test_1-pr_1\test_2&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-pr_1\test_2&a=a&b=b&c=c
        
    All functions that match the input data strucuture in both "pr_1\ctr\test_1" and "pr_1\test_2"
    will run. With this, we can call multiple functions in multiple modules right in one request.
    These functions share the same source data, and do their own work.
    所有在"pr_1\ctr\test_1"和 "pr_1\test_2"中，匹配输入的数据结构的方法都会执行。
    有了这种操作方式，我们可以通过一个请求调用多个模块中的多个方法。
    这些方法共享同一个数据源，完成各自的工作。
        
    strict style:
    严格模式：
    1. http://HostName/api.php&c=pr_1\ctr\test_1-pr_1\test_2-test_a&a=a&b=b&c=c
    2. http://HostName/api.php&cmd=pr_1\ctr\test_1-pr_1\test_2-test_a-test_b&a=a&b=b&c=c
        
    Functions placed in the URL (in "c"/"cmd" value, seperated by "-", order ignored, same in "POST") 
    and match the input data strucuture at the same time in both "pr_1\ctr\test_1" and "pr_1\test_2"
    will run. With this, we can call EXACT multiple functions in EXACT multiple modules in one request.
    These modules share the same function names when exist. 
    All functions share the same source data and run with the input order.
    路径（"c"或"cmd"的值，用"-"符号分隔开，忽略顺序，POST方式相同）中的函数，
    并且在"pr_1\ctr\test_1" 和 "pr_1\test_2"中匹配输入的数据结构的函数会执行。
    我们可以通过一个请求调用多个具体模块中的多个具体的方法。
    这些模块如果存在请求的方法名的话就会执行该方法。
    所有方法共享同一个数据源并且执行顺序与输入数据结构顺序相同。
    
**CLI Command usage:**  
**CLI命令行用法：**
    
    CLI options are as follows:
        
    c/cmd: command
    d/data: CGI data content
    p/pipe: CLI pipe content
    r/ret: process return option
    l/log: process log option (cmd, data, error, result)
    t/time: read time (in microseconds; default "0" means read till done. Works when r/ret or l/log is set)
        
    In Chinese:
    
    c/cmd: command
    d/data: CGI数据内容
    p/pipe: CLI管道内容 
    r/ret: 返回选项
    l/log: 日志选项
    t/time: 读取时间（以ms为单位，默认"0"表示完成后读取，如果r/ret或l/log有设置）
        
    **Examples:
        
    Let's take "pr_1\ctr\test_1" as an example.
    让我们用"pr_1\ctr\test_1"做一个例子。    
    Full command should be as some type of follows:
    完整的命令应该像如下的某些类型：
        
    1. /path/php api.php --ret --cmd "pr_1\ctr\test_1-test_a" --data "a=a&b=b&c=c"
    2. /path/php api.php -r -t 10000 -c "pr_1\ctr\test_1-test_b" -d "b=b&c=c"
    3. /path/php api.php -r -l -c "pr_1\ctr\test_1-test_a-test_b" -d "a=a&b=b&c=c"
    4. /path/php api.php --ret --cmd "pr_1\ctr\test_1-test_a-test_b" --data "a=a&b=b&c=c"
    5. ...
        
    JSON data package is also support as CGI mode
    JSON数据包也支持CGI模式
        
    We can also do as follows:
        
    1. /path/php api.php pr_1\ctr\test_1-test_a -d "a=a&b=b&c=c"
    2. /path/php api.php pr_1\ctr\test_1-test_b -d "b=b&c=c"
    3. /path/php api.php pr_1\ctr\test_1-test_a-test_b -d "a=a&b=b&c=c"
    4. /path/php api.php pr_1\ctr\test_1 -d "a=a&b=b&c=c"
    5. ...
        
    If we need to call external programs, make sure the "c" or "cmd" key is listing in "cfg.ini" 
    with the executable path as the value, or ever more.
    如果我们需要调用外部项目的话，确保在"cfg.ini"文件中有列入"c" or "cmd"，并且以可执行路径作为值
        
    Something examples:
        
    "cfg.ini"
        
    mycmd = "/xxx/path/mycmd"
        
    Command:
        
    1. /path/php api.php mycmd
    2. /path/php api.php -c mycmd a b c
    3. /path/php api.php --cmd mycmd -v a -b b c
    4. ...
        
    If data needs to be sent via pipe:
    如果数据需要通过管道发送的话：
        
    1. /path/php api.php mycmd -p "some data"
    2. /path/php api.php -c mycmd --pipe "some data"
    3. ...
        
    Output data will be also captured as in CGI mode and outputs via STDOUT.
    通过STDOUT和CGI模式也能获取到输出数据
        
    Don't forget to use "-r" or "--ret" to capture output data.
    If time is too short to run extenal programs, use "-t ms" or "--time ms"
    不要忘记使用"-r"或者 "--ret"捕获输出数据。
    如果时间太短而不能执行外部程序的话，可以使用"-t ms" 或者 "--time ms"命令。
        
    In CLI mode, we have 3 globle variables named "PHP_PID", "PHP_CMD" and "PHP_EXE".
    在CLI模式中，有3个全局变量："PHP_PID", "PHP_CMD" 和 "PHP_EXE"
        
    "PHP_PID" is the Process ID that the current PHP runs with.
    "PHP_CMD" is the Process Comand that the current script started with.
    "PHP_EXE" is php executable path, which we can execute another PHP process.
    
    "PHP_PID"：当前php脚本运行的进程ID
    "PHP_CMD"：当前脚本运行的处理命令
    "PHP_EXE"：php脚本的可执行路径
        
    All the globle variables can be fetched in "os::$env".
    所有的全局变量能够通过"os::$env"获取。
    
**About "cfg.php" in Project root directory**  
**关于在项目根目录的"cfg.php"**

Each project could have a "cfg.php" as the only config file for the whole project script, in which we can set some values for extension's variables or some sepcial definitions.  
每个项目都会有一个"cfg.php"作为整个项目唯一的配置文件，这样我们才能设置扩展中需要的变量值或者其他一些特别的定义。  
So that, the scripts in this project will run under these settings.  
这样，项目中的脚本都是在这些设置下执行的。

For example, we can set project 1 to connect database A, but using database B in project 2; We can also set language to "en-US" in project 1, but "zh-CN" in project 2, etc...  
比如，我们设置项目1连接数据库A，让项目2连接数据库B。我们也可以在项目1中设置语言为"en-US"，在项目2中设置语言为"zh-CN"，等等。

But, always remember, don't define same named constants in different "cfg.php"s. It'll conflict.  
但是，永远记住，不要在不同的"cfg.php"定义相同的常量，这会产生冲突。  
All "cfg.php"s existed in the root directory of projects will be required in order right before inside script runs.  
所有在项目根目录的的"cfg.php"需要在项目内的脚本运行前按顺序执行。  
Class variables are encouraged to use instead of definitions in "cfg.php"s.  
我们更推荐使用类变量，而不是在"cfg.php"中定义常量。  

Some examples for "cfg.php":

    //named constants (don't conflict with other "cfg.php"s)
    define('DEF_1', 'xxxx');
    define('DEF_2', 'xxxxxxxx');
        
    //define "keygen" & "ssl_cnf" for "crypt" extension
    \ext\crypt::$keygen = '\demo\keygen';
    \ext\crypt::$ssl_cnf = '/extras/ssl/openssl.cnf';
        
    //define MySQL connection parameters for "pdo_mysql" extension
    \ext\pdo_mysql::$config['init'] = true;
    \ext\pdo_mysql::$config['host'] = '192.168.1.100';
    \ext\pdo_mysql::$config['port'] = 4000;
    \ext\pdo_mysql::$config['pwd'] = 'PASSWORD';
        
    //parameters for "errno" extension
    \ext\errno::$lang = false;
    \ext\errno::load('cars', 'errno');
        
    //parameters for "http" extension
    \ext\http::$send_payload = true;
        
    //More if needed
    ...

If you want to set all variables inside classes. That is OK, just leave the "cfg.php" files away.  
如果你想在类里面设置所有的变量，这也是可行的，只要将"cfg.php"删掉。

If you don't have a "cfg.php" under the root directory of the project, all settings are inherited from the one before.  
如果项目跟目录中没有"cfg.php"文件，那么所有的设置都会继承自上一个cfg.php.

## Notice:
**通知:**

Once if there is only one element in router's result, it will output the inner content value in JSON and ignore the key('namespace/class_name/function_name'). 
If "DEBUG" option (in "/core/cfg.php") is set to 1 or 2, the results could be complex because one or more elements for debugging will be added to results as well.
Always remember to close "DEBUG" option (set to 0) when all are under production environment, or, the result structure will confuse us with more values inside.  
一旦路由的结果中只有一个元素，它将会以JSON格式输出里面的内容作为值，忽略掉('namespace/class_name/function_name')键名。
如果"DEBUG"选项 (在 "/core/cfg.php"中)被设置成1或者2，结果将会非常复杂，因为调试的一个或更多的元素也会被添加到结果中。
当处于生产环境时，或者，结果结构因为包含很多的值而使我们困惑的时候，永远记住关掉 "DEBUG"选项（将它设置成0）。

## Demos
Version 5.0.0 is on going, and not compatible with versions before.  
版本5.0与之前的版本是不兼容的。

Demos for Ver 5.0.0 is here: [DEMO](https://github.com/Jerry-Shaw/demo). Just get it a try.  
版本5.0的demo地址：[DEMO](https://github.com/Jerry-Shaw/demo). 不妨试一试
    
## Credits
pdo_mysql Extension: [shawn](https://github.com/phpxiaowei)  
Chinese Translation: [MileHan](https://github.com/MileHan)

## Old Version:
Old version before 3.0.0 is discontinued and the source codes located here: [3.2.0](https://github.com/Jerry-Shaw/NervSys/tree/3.2)  
3.0以前的老版本已经停止了更新，源码位置：[3.2.0](https://github.com/Jerry-Shaw/NervSys/tree/3.2)  

## Licensing

This software is licensed under the terms of the GPLv3.  
You can find a copy of the license in the LICENSE file.