# Nervsys

稳定版本: 7.2.20  
扩展版本: 2.0  
[英文文档](https://github.com/NervSys/NervSys/blob/master/README.md)  
[单元测试用例](https://github.com/NervSys/tests)  
  
如果遇到问题，请告诉我们.  
当你有更好的想法时请Pull Request  
感谢你的支持.  

## 关于

* Nervsys是什么?  
一个基于php7.2+的轻量级通用API开发框架.  

* 为什么叫"Nervsys"?  
初心不改， 这个单元像神经细胞一样处理多个任务，并且组合构建成一个纯粹基于数据的调用系统，不需要多余的命令告诉系统应该做什么

* 环境要求:  
PHP 7.2+ 及以上,任何类型的Web服务器或CLI模式  

* 应用场景:  
    1. 普通的网页开发框架
    2. 适用于所有类型应用程序的API控制器
    3. 程序通信客户端
    4. 更多...


## 结构

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
    │     ├─pool/
    │     │     ├─command.php                   command data pool
    │     │     ├─process.php                   process date poll
    │     │     └─setting.php                   setting data pool
    │     ├─system.ini                          system setting file
    │     └─system.php                          system script file
    ├─ext/
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
    ├─font/
    ├─logs/
    └─api.php                                   API entry script


## 保留关键字
  
CGI: c/cmd, m/mime  
CLI: c/cmd, m/mime, d/data, p/pipe, t/time, r/ret  
  
解释:  
r/ret: 返回选项 (No needed value)  
c/cmd: 系统命令 (User defined)  
m/mime: 输出 MIME 类型 (json/xml/html, UTF-8, default: json)  
d/data: CLI 数据包 (Transfer to CGI progress)  
p/pipe: CLI 管道数据包 (Transfer to CLI programs)  
t/time: CLI 读取超时时间 (in microsecond, default: 0, wait till done)  
  
Nervsys核心保留了以上关键字。因此，小心使用。


## 配置 "system.ini"

"system.ini" 位于"core"文件夹的正下方，其中包含大部分重要的配置信息.

### SYS

    [SYS]
    保留系统设置
    timezone = PRC

### LOG

    [LOG]
    这里是全局配置，包含自定义设置的日志级别
    
    
    emergency = on
    alert = on
    critical = on
    error = on
    warning = on
    notice = on
    info = on
    debug = on
    
    display = on ; 在屏幕显示日志
    
    如果不需要记录或显示它们，不要删除这些级别，把它们的值改成"off" 或者 "0".

### CGI

    [CGI]
    这里是 CGI 进程配置，包含所有自定义命令映射.
    
    
    举例 & 解释:
    
    设置: MyCMD = dirA/dirB/model-func
    使用: cmd=MyCMD
    解释: 发送 "cmd=MyCMD", 它将被重定向到 "dirA/dirB/model-func"
    
    设置: MyCMD = dirA/dirB/model
    使用: cmd=MyCMD-func
    解释: 发送 "cmd=MyCMD-func",  "MyCMD" 会被 "dirA/dirB/model" 代替
    
    设置: MyCMD = dirA/dirB
    使用: cmd=MyCMD/model-func
    解释: 发送 "cmd=MyCMD-func", "MyCMD-func", "MyCMD" 会被 "dirA/dirB" 代替


### CLI

    [CLI]
    这里是 CLI 进程配置，包含所有有效的本地程序命令
    
    
    举例 & 解释:
    
    设置: MyCMD = /xxx/path/mycmd
    使用: --cmd="MyCMD"
    解释: 发送 "cmd=MyCMD", 将改为调用"/xxx/path/mycmd"
    
    设置: MyCMD = /xxx/path/mycmd -a -b --more
    使用: --cmd="MyCMD"
    解释: 发送 "cmd=MyCMD",  将改为调用"/xxx/path/mycmd -a -b --more"
    
    注意: 无论如何，这里未配置的程序都不会被调用
    

### CORS

    [CORS]
    这里是 HTTP 请求配置，包含跨源资源共享设置.
    
    
    举例 & 解释:
    
    设置:
    http://your.domain.com = X-Requested-With, Content-Type, Content-Length
    https://your.domain.com = X-Requested-With, Content-Type, Content-Length
    http://your.domain.com:800 = X-Requested-With, Content-Type, Content-Length, Custom-Header

    解释:
    所有从本节中上述域通过Ajax发出的请求，都被允许并接受头部信息。

    注意:
    如果被设置成 "*" ,  CORS 将对所有域名中带着已定义头部进来的请求开放.
    
    
### INIT

    [INIT]
    这里是全局配置，包含系统启动初始化调用方法
    This section holds system startup initial functions.
    
    举例 & 解释:
    
    单个设置:
    SomeDesc = dirA/dirB/model-func
    
    解释: 
    "\dirA\dirB\model::func($params)" 将在启动时通过所需的参数调用. 
    
    
    多重设置:
    DescA = dirA/dirA/model
    DescB = dirA/dirB/model-funcA
    DescC = dirA/dirB/model-funcB
    DescD[] = dirC/dirC/model-funcA
    DescD[] = dirC/dirC/model-funcB
    
    解释: 
    "\dirA\dirB\model::funcA($params)" & "\dirB\dirB\model::funcB($params)"
    将在启动时通过所需的参数调用. 
    然而, 因为没有设置函数， "\dirC\dirC\model::__construct($params)"将被调用.
    
    注意: 
    在输入被读取之前调用准备状态 (S1)，这部分配置中的键在系统中不起作用，但是开发者应该知道它们是什么
    如果在值中没有指定函数，则将调用构造方法 "__construct"。
    这里允许数组设置，所需参数将自动传递，所有存在的返回值将被捕获. 

    建议: 
    在没有必要的情况下不用返回. 
    
### LOAD

    [LOAD]
    这里是全局配置，包含"/subfolder/"启动初始化调用方法.
    
    
    举例 & 解释:
    
    单个设置:
    dirA = dirB/model-func
    
    解释: 
    "\dirB\model::func($params)" 在调用 dirA 下的函数之前只调用一次. 
    
    
    多重设置:
    dirA = dirX/model-func
    dirB[] = dirX/model-funcA
    dirB[] = dirX/model-funcB
    dirC = dirY/model
    
    解释: 
    "\dirX\model::func($params)" 在调用 dirA 下的函数之前只调用一次.
    "\dirX\model::funcA($params)" & "\dirX\model::funcB($params)" 在调用 dirB 下的函数之前，将只调用两个函数一次。

.
    然而, 因为没有设置函数， "\dirY\model::__construct($params)" 将被调用, 只在调用 dirC 下的函数之前调用一次, 
    
    
    注意: 
    在访问第一级子文件夹时调用进程状态（s2）。这里键指向第一级子文件夹，而值指向访问子文件夹时将调用的函数。 
    如果在值中没有指定函数，则将调用构造方法 "__construct"。
    这里允许数组设置，所需参数将自动传递，所有存在的返回值将被捕获. 

    建议: 
    在没有必要的情况下不用返回. 


### PATH

    [PATH]
    这里是自动加载功能配置，包含要自定义include路径，仅适用于非命名空间类include路径

    
    举例:
    
    1 = pathA ; 根目录的相对路径
    2 = pathA/pathB/ ; 根目录的相对路径
    3 = pathA\pathB ; 根目录的相对路径
    4 = pathA\pathB\ ; 根目录的相对路径
    5 = /pathB/ ; 绝对路径
    6 = /pathA/pathB ; 绝对路径
    7 = /pathA/pathB/ ; 绝对路径
    8 = \pathA\pathB ; 绝对路径
    9 = \pathA\pathB\ ; 绝对路径
    svr_path = D:\server\lib ; 绝对路径
    lib_path = E:\lib\ ; 绝对路径
    some_key = F: ; 绝对路径
    ...

    注意: 
    路径的最后一个 "/" 不是必需的.  
    这部分配置中的键在系统中不起作用，但是开发者应该知道它们是什么.  
    

## 举例
    
### 结构举例:

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


### 举例 "TestA.php":

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


### 举例 "TestB.php":

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

Nervsys中的 "error_reporting" 不再重要了。
所有错误和异常都将被很好地处理到日志/或显示中。
但它保留了发生错误时的退出级别和JSON/XML结果的格式。
当"error_reporting"设置为大于0时，将在JSON/XML结果中添加换行符

### Factory
  
在最新版本的Nervsys中，"factory" 处理程序可用于控制所有扩展类。
  
使用:  
所有从 "factory"扩展的类都可以如下使用:  
  
* 从被调用类创建新的克隆类:  
$class = class_name::new(arguments, ...);  
  
* 以别名命名:  
$class = class_name::new(arguments, ...)->as('alias_name');  
  
* 将其保存在别名下并进行配置:  
$class = class_name::new(arguments, ...)->config(array $settings)->as('alias_name');  
  
* 通过对象的别名获取克隆对象:  
$cloned_class = class_name::use('alias_name');  
  
* 以中间的方式保存对象:  
$object->as('alias_name'); or $object->config(array $settings)->as('alias_name'); 
  
* 从工厂中释放(销毁):  
$object->free(); or $object->free('alias_name');  
  
  
注意: 调用 "use" 和 "obtain" 方法的方法是相同的，但是仍然有一些小的区别:  
  
**new**: 返回的对象为调用此方法的类，但处于克隆模式，所传参数是 "__construct" 方法的参数.  
  
**use**: 返回的对象为调用此方法的类，直接指向由 "as" 方法存储的原始实例，非克隆实例。只接受唯一参数，存储时的别名.  
  
**obtain**: 返回的对象为第一个参数传入的类名，第二个参数为该类中 "__construct" 方法的参数.  
  
  
**Caution**:  
1. 确保按条件和不同方式使用别名，以避免冲突.  
2. 确保调用顺序与预期一致，特别是在使用factory到new/use类时，检查所有"__construct"方法中的子调用入口。工厂将生成不正确的对象，即使 "new" 显然是在 "use" 之前调用的，而 "use" 是在 "new" 之前调用的类的"__construct"内部调用的。  
  
  
### TrustZone

每个暴露给API的类都应该包含一个名为"$tz"的静态或非静态变量，它控制类中精确的方法调用行为.  
  
当API第一次访问该类时，将记录这些值。永远不要试图在同一个类中的任何函数中修改"$tz"。不会有任何作用.  
  
 "$tz" 中的键:公开给API的函数.  
 "$tz" 中的值:控制调用目标函数的API操作.  
  
在"$tz"中，在需要时把"pre", "post" 和 "param"键组合使用，操作如下:  
1. 以"pre"的值执行所有预置函数.  
2. 根据"param" 设置执行目标方法.  
3. 以"post"的值执行所有预置函数.  
  
没有在"$tz"中列出的函数不会被API直接调用。.  
  
当输入数据与预置值不匹配时，API将跳过调用目标函数及其"post"依赖项，并抛出警告异常.  
  
一旦在TrustZone流程周期中出现任何失败，例如缺少"pre"/"post"依赖项方法、缺少函数参数等。API将跳过流程周期的其余部分，并抛出一个警告异常。其他流程周期将继续运行。

  
_数组格式的两种$tz类型: 示例"TestA.php"_  
  
在上面的例子中，函数 "test_a" 的$tz是在简单模式下编写的，而函数 "test_b" 的$tz是在完全模式下编写的
  
在简单模式中，内容是函数必须存在的参数。当输入数据结构与$tz设置不匹配时，API将忽略这些函数 
   
在完全模式下，必须存在的参数列在'param'键下，它们在做同样的事情。'pre'键控制预运行方法，而'post'键控制后运行方法。这两个设置在函数调用之前/之后执行。  
  
_Simple $tz in string format: example of "TestB.php_  

这是一种简单的格式，意味着"test_a", "test_b", "test_c"方法都公开给API，没有TrustZone限制。但是它们仍然会被参数数据解析器检查。 
  
注意: 我们可以将TrustZone的值设置为"*"，以便简单地将所有公共方法公开给API。  
  
  
### 自动填充

函数中的参数一旦存在相同名称的进程数据中，将由API自动填充。注意，这个特性只适用于API公开的函数。开始调用函数后，将检查$tz和参数，以确定是否通过.


### 简单调用

作为一个基本的支持功能，它在web开发中工作得很好。  
  
Method: GET, POST  
DataType: application/x-www-form-urlencoded, application/json, application/xml, multipart/form-data  
  
GET examples: 
api.php?cmd=DirA/ctr/TestA-test_a&param_a=xxx&param_b[]=yyy&param_b[]=zzz
api.php?cmd=DirA/ctr/TestA-test_b&param_c=xxx&param_d[]=yyy&param_d[]=zzz
api.php?cmd=DirA/ctr/TestA-test_c&param_a=xxx&param_c=xxx&param_b[]=yyy&param_b[]=zzz


### Multiple-Calling & Judging-Calling

如前所述，它有望像神经细胞一样处理数据，必须支持多重呼叫和判断呼叫。  
  
Method: GET, POST  
DataType: application/x-www-form-urlencoded, application/json, application/xml, multipart/form-data  
  
假设，"TestB.php" 在示例中，函数和$tz具有以下API所需的允许参数。

**Multiple-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Result:  
将调用所有符合$tz & param检查的请求函数。所有结果数据包都将放在对应的函数名键下，类名作为前缀。 


**Judging-Calling examples:**

Calling in SINGLE class:  
api.php?cmd=DirA/ctr/TestA&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Calling in MULTIPLE class:  
api.php?cmd=DirA/ctr/TestA-DirA/TestB&param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz  
  
Result:  
将检查$tz中的所有功能。那些符合$tz & param检查的将被调用。所有结果数据包都将放在对应的函数名键下，类名作为前缀。 


## 命令行

在这种模式下，当命令和数据匹配类和函数结构时，将调用PHP脚本。当command被限定时，[CLI]小节中列出的外部程序也将被调用。这两个结果都可以捕获并在它们的键名下组合。 


### CLI 选项

    r/ret: 返回选项 (仅在CLI可执行模式下可用)
    c/cmd: 系统命令 (通过"-"分隔)
    m/mime: 输出 MIME 类型 (json/xml/html, 默认: json, 当设置"r/ret"时可用)
    d/data: CLI 数据包 (传输到CGI进程)
    p/pipe: CLI 管道数据包 (传输到CLI程序)
    t/time: CLI 读取超时 (在微秒内，默认值为0，等待完成)


### CLI 使用

**不同的选项示例:两者作用相同.**

* /path/php api.php -r -c "DirA/ctr/TestA-test_a" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz"


**更多示例:**

* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-test_b-test_c" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"  
* /path/php api.php -r -c "DirA/ctr/TestA-DirA/TestB" -d "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz"


**外部调用示例:**

* /path/php api.php --cmd "MyCMD" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "MyCMD" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "MyCMD_A-MyCMD_B" --pipe "xxxxxxxx" --time "1000"  


**混合调用示例:**

* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-MyCMD" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-test_a-test_b-test_c-DirA/TestB-test_a-test_b-test_c-MyCMD_A" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz" --pipe "xxxxxxxx"  
* /path/php api.php --ret --cmd "DirA/ctr/TestA-MyCMD_A-MyCMD_B" --data "param_a=xxx&param_b[]=yyy&param_b[]=zzz&param_c=xxx&param_d[]=yyy&param_d[]=zzz" --pipe "xxxxxxxx" --pipe "xxxxxxxx" --time "1000"  
  

## On Error

通常，当php出现错误或异常时，它会停止。但是在这里，它只在出现错误时停止，即使缺少依赖函数，或者没有将代码设置为E_USER_ERROR的异常，等等……在执行多个调用时非常有用(尚未最终完成)
  

## Credits

[kristenzz](https://github.com/kristemZZ)  
[tggtzbh](https://github.com/tggtzbh)  
[xushuhui](https://github.com/xushuhui)  


## Licensing

This software is licensed under the terms of the Apache 2.0 License.  
You can find a copy of the license in the LICENSE.md file.
