# Nervsys

README: [English](README.md) | [简体中文](README_zh-CN.md)

[![release](https://img.shields.io/badge/release-8.0.0-blue?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/releases)
[![issues](https://img.shields.io/github/issues/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/issues)
[![contributors](https://img.shields.io/github/contributors/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/graphs/contributors)
[![last-commit](https://img.shields.io/github/last-commit/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/commits/master)
[![license](https://img.shields.io/github/license/Jerry-Shaw/NervSys?style=flat-square)](https://github.com/Jerry-Shaw/NervSys/blob/master/LICENSE.md)
[![QQ](https://img.shields.io/badge/QQ交流群-191879883-lightgrey?style=social)](https://qm.qq.com/cgi-bin/qm/qr?k=FJimjw1l5qKXGdDVSmyoq2-PTQ2ZTqBy&jump_from=github)  

## 关于 Nervsys

* 什么是"Nervsys"?  
一个非常轻便的PHP开发框架，使用和集成都相当方便。  

* 为什么要取名为"Nervsys"?  
最开始，我们希望他能像神经细胞一样工作，相互结合起来，各自分工，可以形成以数据为导向的处理系统，并不需要依赖具体的处理命令。

* 有小名么?  
**NS**, 我们大部分人都这么叫他，但是，不要跟任天堂的NS游戏机搞混了.  

* 系统需求:  
PHP **7.4+** 及以上。任意的Web服务器环境或者在命令行下运行他。  

* 用途举例:  
    1. 普通的网站后端开发框架
    2. 各类App的后端接口控制器框架
    3. 程序通信控制端
    4. 其他...

## 安装

1. 克隆或者下载源码，保存到目标机器的任意位置。每台机器只需要一份副本即可，哪怕是有多个项目一起在运行。
2. 在项目入口脚本中引用"NS.php"，然后通过"NS::new();"来启动系统。
3. 如果需要，在"NS::new();"启动系统前，使用"/Ext/libCoreApi"来注册自己的模块和方法。
4. 如果一切没有改动，在项目下"/api"目录里面写项目api类，在"/app"目录下，写业务类即可。
5. 在"/Ext"目录中有很多常用的扩展类，对于一般项目开发来说，都非常有用。所以，希望你能在开发的时候，多看看里面的扩展类，说不定能帮上忙。

## 用法

###### 注意: 所有用例代码均以系统默认设置为基础。



#### 1. 建议的项目结构

```text
Root/
    ├─api/                            默认API入口代码目录
    │    └─DemoApiClass.php           API DEMO类文件
    ├─app/                            默认业务代码目录
    │    └─DemoAppClass.php           业务代码DEMO类文件
    ├─config/                         建议配置文件目录 (使用 "Ext/libConfGet.php" 加载解析)
    │       ├─dev.conf                dev配置文件
    │       ├─prod.conf               prod配置文件
    │       └─...                     其他配置文件
    ├─message/                        建议消息文件目录 (使用 "Ext/libErrno.php" 加载解析)
    │        └─msg.ini                自定义消息文件
    └─home/                           默认web目录
          └─index.php                 入口脚本
```

#### 2. NS 集成

跟着 "[安装](#安装)" 的步骤，把NS整合到入口文件中。样例代码如下：

```php
require __DIR__ . '/../../NervSys/NS.php';

//可选，如果需要的话，请参阅"Ext/libCoreApi.php"
\Ext\libCoreApi::new()
    //打开核心调试模式 (错误信息会随着结果显示出来)
    ->setCoreDebug(true)
    //打开全局跨域许可 (默认请求头)
    ->addCorsRecord('*')
    //设置输出格式为"application/json; charset=utf-8"
    ->setContentType('application/json');

NS::new();
```

#### 3. 请求数据格式

NS可以接续通过GET和POST传过来的参数，FormData and request Payload都可以解析。  
当数据通过request Payload传输过来时，JSON和XML都支持直接解析。  
NS的数据捕获和解析类位于"/Core/Lib/IOUnit.php"。  

在HTTP请求中，NS会依照如下步骤获取和解析数据包:

```text
1. 从HTTP请求中读取Accept，如果入口没定义，将以此来决定返回类型。
2. 从URL中读取，尝试通过"PATH_INFO"或者"REQUEST_URI"来找到"c"。
3. 按照 FILES -> POST -> GET 顺序从 FormData 中以非覆盖模式获取传入参数。
4. 获取 request Payload，尝试以 JSON/XML 格式来解析数据包，并将解析好的数据并入以上数据包。
5. 按照入口文件中指定的key来读取HTTP Header和Cookie数据，然后并入到以上数据包。
6. 从数据源中寻找并分离出"c"，当作请求指令传给路由类处理。
```

在CLI模式下，NS读取"-c"参数当成"c"，或者没找到"-c"的时候，获取第一个参数当成"c"，字符串类型的参数"-d"会被解析后当成CGI的数据源，其他剩余参数，会被当做CLI命令行参数。

#### 4. 关于 "c"

"c" 参数会被当成请求指令，指导系统继续执行后续操作。  
"c" 可以通过各种形式传入，URL, GET, POST 都可以，也不需要关心是 FormData 还是 request Payload.  

在CGI模式下，也就是大家所知的HTTP请求，因为一些安全因素考虑，"c" 永远都是默认重定向到api路径下，但是，CLI模式下允许直接调用root路径下命令，只需要在"c"前面加上"/"，并使用完整的类命名空间路径即可。  

有效的 "c" 参数格式如下:  

```text
基于API路径: innerpath_in_api_path/class_name/public_method_name

例子:
URL: http://your_domain/index.php/user/login => 调用 "\api\user" 类里面的 "login" 方法。
URL: http://your_domain/index.php/user/info/byId => 调用 "\api\user\info" 类里面的 "byId" 方法。

GET: http://your_domain/index.php?c=user/login
POST: 直接用参数"c"传入"user/login"，FormData 或 request Payload 都支持。
```

```text
基于ROOT路径: /namespace/class_name/public_method_name

例子:
URL: 不支持.

CLI: php index.php /app/user/login => 调用 "\app\user" 类里面的 "login" 方法。
CLI: php index.php -c"/app/user/login" => 调用 "\app\user" 类里面的 "login" 方法。

GET: http://your_domain/index.php?c=/app/user/login
POST: 直接用参数"c"传入"/app/user/login"，FormData 或 request Payload 都支持。
```

#### 5. 数据自动填充

当系统获取到"c"和数据源的之后，路由类和执行类会被唤醒，调用具体的方法来处理。键名匹配的参数会被从数据源冲提取出来，并按照正确的顺序排列，在函数调用时自动传给目标函数。要注意所有传输给NS的参数，所有键名都是大小写敏感的，键值是类型严格限制的。所有返回的结果数据会被捕获和输出。

例子:

```text
参数的顺序不定，可以是任何顺序:
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
        //你的代码

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
        //你的代码

        return $name . ' is ' . $age . ' years old.';
    }
}
```

#### 6. 暴露的核心类

从8.0开始，NS暴露了比较重要的核心库给开发者。  
感谢 [douglas99](https://github.com/douglas99)，所有核心可变API都整理到了"Ext/libCoreApi.php"里面。  
有了它，开发者可以注册自己的类库，替代系统默认的类库，比如：路由类，输出处理库，Api路径设置，钩子相关的函数等...

## 待办
- [x] 基础核心和扩展逻辑
- [x] 自动化参数映射
- [x] 应用代码运行环境监测逻辑
- [x] 第三方路由模块支持
- [x] 第三方错误处理模块支持
- [x] 第三方数据读取/输出模块支持
- [x] 基于路径的钩子函数注册功能支持
- [ ] Socket 相关功能
- [ ] ML/AI 相关内置路由
- [ ] 更多详细的文档和范例

除了上述功能以外，NS还有很长的路要走。    
当你发现bug或有优化修复想法的时候，欢迎提交issue和pull request，或者仅仅是想获取帮助。联系我们吧。

## 支持机构

感谢 [JetBrains](https://www.jetbrains.com/?from=Nervsys) 提供的开源许可证对本项目的支持。  

## 开源协议

本软件使用 Apache License 2.0 协议，请严格遵照协议内容发行和传播。  
您能在 [LICENSE.md](https://github.com/Jerry-Shaw/NervSys/blob/master/LICENSE.md) 中找到该协议内容的副本。