# Nervsys - 极简 PHP 框架

[![release](https://img.shields.io/badge/release-8.3.0-blue?style=flat-square)](https://github.com/Jerry-Shaw/Nervsys/releases)
[![php](https://img.shields.io/badge/php-8.1+-brightgreen?style=flat-square)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-Apache%202.0-blue?style=flat-square)](LICENSE.md)

### README: [English](README.md) | [简体中文](README_zh-CN.md)

## 概述

**Nervsys**（源自 "Nerve System"）是一个极简、高性能的 PHP 框架，专为现代化 Web 应用和 API
开发设计。框架设计理念借鉴神经系统，旨在像神经细胞一样灵活处理数据流，帮助开发者构建基于纯数据调用的智能应用系统。

## 🚀 核心特性

- **轻量级设计**：核心精简，无冗余依赖
- **智能路由**：自动参数映射，减少重复代码
- **双模支持**：同时支持 Web (CGI) 和命令行 (CLI) 模式
- **模块模式**：将代码组织成独立模块，每个模块拥有自己的入口文件和元信息 – 适用于大型可复用应用
- **全面安全**：内置 XSS 防护、CORS 支持、API 安全验证
- **性能分析**：内置性能监控和优化工具
- **并发处理**：支持纤程、多进程、Socket 通信
- **高度可扩展**：模块化设计，易于定制和扩展

## 📋 环境要求

- PHP 8.1 或更高版本
- 支持 CLI 和 Web 运行模式
- 常见的 Web 服务器（Apache/Nginx/IIS）或直接 CLI 运行

---

## 🚦 快速开始

### 安装

1. **克隆框架源码**

```bash
git clone https://github.com/Jerry-Shaw/Nervsys.git
```

> 💡 **提示**：一台服务器只需要一套 Nervsys 框架即可运行多个项目

2. **创建项目结构**

```
project_root/
├── api/           # API 接口类（默认模式）
├── modules/       # 模块目录（模块模式）
├── app/           # 应用类
├── www/           # Web 入口目录
│   └── index.php  # 主入口文件
└── logs/          # 日志目录（自动创建）
```

3. **配置入口文件** (`www/index.php`)

```php
<?php
require __DIR__ . '/../Nervsys/NS.php';

$ns = new Nervsys\NS();

// API 模式（默认）
$ns->setApiDir('api')
   ->setDebugMode(true)
   ->setContentType('application/json')
   ->go();
```

---

## 📖 使用指南

### 创建第一个 API（API 模式）

1. **定义 API 类** (`api/User.php`)

```php
<?php
namespace api;

class User
{
    public function login(string $username, string $password): array
    {
        return ['success' => true, 'token' => md5($username)];
    }
}
```

2. **调用 API**：`GET /index.php?c=User/login&username=admin&password=123`

### 创建第一个模块（模块模式）

模块模式是一种替代路由策略，每个模块是一个独立目录。

1. **在入口文件中启用模块模式**：

```php
$ns->setMode('module')->setApiDir('modules')->go();
```

2. **创建模块目录**：`modules/calculator/`

3. **添加 `module.json`**：

```json
{
  "name": "calculator",
  "version": "1.0.0",
  "entry": "go.php"
}
```

4. **创建入口文件** `modules/calculator/go.php`：

```php
<?php
namespace modules\calculator;
class go {
    public function add(int $a, int $b): array {
        return ['result' => $a + $b];
    }
}
```

5. **调用模块**：`GET /index.php?c=calculator/add&a=5&b=3`

> 模块模式同样支持 CLI：`php index.php -c"calculator/add" -d'a=5&b=3'`

---

## 🎯 快速上手指引

Nervsys 采用 **“约定优于配置”** 的设计理念，让您能够跳过繁琐的配置步骤，直接基于智能的默认设置开始高效开发。

### 📌 核心原则

1. **配置集中化**  
   所有框架配置功能都集成在 `System Trait` 中，您无需在不同文件中寻找配置选项，一站式搞定所有设置。

2. **初始化极简**  
   在入口文件中，通过 `new NS()` 创建实例后，只需链式调用您需要的配置方法，即可快速完成框架初始化。

3. **按需学习，渐进深入**
    - **初学者**：专注于编写您的业务 API，无需关心底层实现
    - **进阶用户**：需要时再了解路由、中间件等特定模块
    - **高级开发**：按需探索并发处理、Socket 通信等高级功能

### 🛠️ 扩展支持

- **内置扩展类**：常用功能增强类已预置在 `Ext` 目录中，开箱即用
- **扩展文档**：详细的扩展类使用手册正在积极构建中，敬请期待

### 📖 扩展文档

| 组件 | 文档 |
|:----:|:----:|
| libCache | [📖 English](Ext/README/libCache-en_us.md) \| [📖 中文](Ext/README/libCache-zh_cn.md) |
| libCaptcha | [📖 English](Ext/README/libCaptcha-en_us.md) \| [📖 中文](Ext/README/libCaptcha-zh_cn.md) |
| libCrypt | [📖 English](Ext/README/libCrypt-en_us.md) \| [📖 中文](Ext/README/libCrypt-zh_cn.md) |
| libFileIO | [📖 English](Ext/README/libFileIO-en_us.md) \| [📖 中文](Ext/README/libFileIO-zh_cn.md) |
| libGit | [📖 English](Ext/README/libGit-en_us.md) \| [📖 中文](Ext/README/libGit-zh_cn.md) |
| libHttp | [📖 English](Ext/README/libHttp-en_us.md) \| [📖 中文](Ext/README/libHttp-zh_cn.md) |
| libImage | [📖 English](Ext/README/libImage-en_us.md) \| [📖 中文](Ext/README/libImage-zh_cn.md) |
| libKeygen | [📖 English](Ext/README/libKeygen-en_us.md) \| [📖 中文](Ext/README/libKeygen-zh_cn.md) |
| libLock | [📖 English](Ext/README/libLock-en_us.md) \| [📖 中文](Ext/README/libLock-zh_cn.md) |
| libLog | [📖 English](Ext/README/libLog-en_us.md) \| [📖 中文](Ext/README/libLog-zh_cn.md) |
| libMySQL | [📖 English](Ext/README/libMySQL-en_us.md) \| [📖 中文](Ext/README/libMySQL-zh_cn.md) |
| libPDO | [📖 English](Ext/README/libPDO-en_us.md) \| [📖 中文](Ext/README/libPDO-zh_cn.md) |
| libPlugin | [📖 English](Ext/README/libPlugin-en_us.md) \| [📖 中文](Ext/README/libPlugin-zh_cn.md) |
| libProfiler | [📖 English](Ext/README/libProfiler-en_us.md) \| [📖 中文](Ext/README/libProfiler-zh_cn.md) |
| libQueue | [📖 English](Ext/README/libQueue-en_us.md) \| [📖 中文](Ext/README/libQueue-zh_cn.md) |
| libRedis | [📖 English](Ext/README/libRedis-en_us.md) \| [📖 中文](Ext/README/libRedis-zh_cn.md) |
| libSessionOnRedis | [📖 English](Ext/README/libSessionOnRedis-en_us.md) \| [📖 中文](Ext/README/libSessionOnRedis-zh_cn.md) |
| libSignature | [📖 English](Ext/README/libSignature-en_us.md) \| [📖 中文](Ext/README/libSignature-zh_cn.md) |
| libUpload | [📖 English](Ext/README/libUpload-en_us.md) \| [📖 中文](Ext/README/libUpload-zh_cn.md) |
| libZip | [📖 English](Ext/README/libZip-en_us.md) \| [📖 中文](Ext/README/libZip-zh_cn.md) |

---

💡 **开发心法**：先让它跑起来，再按需逐步优化调整。Nervsys 的设计理念就是让您快速起步，平滑过渡到高级功能。

---

## 🏗️ 核心组件

### 系统配置 (System Trait)

**功能**：提供完整的框架配置接口，包含了所有运行时配置选项，是框架的核心控制中枢。

**主要配置方法**：

**路径与目录配置**：

- `setRootPath($path)` - 设置应用根目录
- `setApiDir($dir_name)` - 设置 API 目录（模块模式下为模块根目录）
- `addAutoloadPath($path, $prepend = false)` - 添加自动加载路径

**运行环境配置**：

- `setTimezone($timezone)` - 设置时区
- `setDebugMode($debug_mode)` - 设置调试模式
- `setLocale($locale)` - 设置区域语言

**模式配置**：

- `setMode($mode)` - 切换模式（`api` 默认，`module` 模块模式）

**安全与跨域配置**：

- `addCorsRule($allow_origin, $allow_headers = '', $expose_headers = '')` - 添加 CORS 规则
- `addXssSkipKeys(...$keys)` - 设置 XSS 过滤跳过键

**钩子系统**：

- `assignHook($hook_fn, $target_path, ...$exclude_path)` - 注册钩子函数

**错误处理**：

- `addErrorHandler($handler)` - 添加错误处理器

**数据 I/O 配置**：

- `readHeaderKeys(...$keys)` - 指定读取的 HTTP 头部键
- `readCookieKeys(...$keys)` - 指定读取的 Cookie 键
- `setContentType($content_type)` - 设置响应内容类型

**性能监控**：

- `setProfilerThresholds($memory_bytes, $time_milliseconds)` - 设置性能分析阈值

**示例**：

```php
$ns = new Nervsys\NS();
$ns->setRootPath('/var/www/app')
    ->setMode('module')
    ->setApiDir('modules')
    ->setDebugMode(true)
    ->go();
```

### 应用环境 (App 类)

**功能**：管理应用运行环境和配置信息，提供全局访问点。

**主要方法**：

- `setMode($mode)` - 设置运行模式（`api` 或 `module`）
- `setRoot($root_path)` - 设置根目录
- `setApiDir($api_dir)` - 设置 API/模块目录
- `setLocale($locale)` - 设置区域
- `setDebugMode($core_debug)` - 设置调试模式

**环境信息属性**：

- `$client_ip` - 客户端 IP 地址
- `$user_lang` - 用户语言偏好
- `$user_agent` - 用户代理
- `$is_cli` - 是否 CLI 模式
- `$is_https` - 是否 HTTPS 协议

**示例**：

```php
$app = App::new();
echo "模式: " . $app->mode;
echo "客户端 IP: " . $app->client_ip;
```

### 错误处理 (Error 类)

**功能**：统一的错误和异常处理系统，提供优雅的错误恢复机制。

**主要方法**：

- `saveLog($app, $log_file, $log_content)` - 保存日志文件
- `formatLog($err_lv, $message, $context = [])` - 格式化日志内容
- `exceptionHandler($throwable, $report_error = true, $stop_on_error = true)` - 异常处理器

**错误级别**：

- **error**：致命错误（E_ERROR, E_PARSE 等）
- **warning**：警告错误（E_WARNING, E_USER_WARNING 等）
- **notice**：通知信息（E_NOTICE, E_DEPRECATED 等）

**示例**：

```php
$error = Error::new();
$error->addErrorHandler([$monitor, 'trackError']);
$logContent = $error->formatLog('warning', 'API 调用频率过高');
$error->saveLog($app, 'security.log', $logContent);
```

### 钩子系统 (Hook 类)

**功能**：提供灵活的中间件和前置处理机制，实现横切关注点分离。

**主要方法**：

- `assign($hook_fn, $target_path, ...$exclude_path)` - 注册钩子函数
- `run($full_cmd)` - 执行匹配的钩子

**钩子特性**：

- 路径前缀匹配 - 精确控制钩子的作用范围
- 排除路径支持 - 灵活配置例外情况
- 参数自动注入 - 简化钩子函数的参数获取
- 流程中断控制 - 钩子返回非 true 时，请求流程会被中断

**示例**：

```php
$hook = Hook::new();
$hook->assign([$auth, 'checkToken'], '/api/', '/api/auth/login');
if ($hook->run('/api/user/getInfo')) {
    // 继续处理
}
```

### 对象工厂 (Factory 类)

**功能**：提供智能对象创建和依赖注入，简化对象生命周期管理。

**主要方法**：

- `new()` - 创建当前类实例
- `getObj($class_name, $class_args = [], $user_passed = false)` - 获取对象实例
- `buildArgs($param_reflects, $data_package)` - 构建参数数组

**特性**：

- 自动依赖注入
- 对象复用（单例模式）
- 参数自动映射
- 类型安全转换

**示例**：

```php
class UserService extends \Nervsys\Core\Factory {
    public function __construct(\Nervsys\Ext\libMySQL $db, int $user_id) {}
}
$service = UserService::new(['user_id' => 1]);
```

### 路由系统 (Router 类)

**功能**：处理请求路由解析，同时支持 Web 和命令行双模式，提供高度灵活的路由机制。

**主要方法**：

- `parseCgi($c)` - 解析 Web 请求路由
- `parseCli($c)` - 解析命令行请求路由
- `addCgiRouter($router)` - 添加 Web 路由处理器
- `addExePathMapping($exe_name, $exe_path)` - 添加可执行文件映射

**路由特性**：

- 路由栈机制（优先级处理）
- 自定义路由处理器
- 可执行文件映射
- 路径规范化

**示例**：

```php
$router = Router::new();
$router->addCgiRouter(function($path) {
    if (str_starts_with($path, 'v2/')) {
        return ['Api\\V2\\' . str_replace('/', '\\', substr($path, 3)), 'handle'];
    }
    return [];
});
```

- **外部程序映射**：对于需要执行外部程序的 CLI 命令（例如 `python script.py`），您可以通过
  `addExePathMapping($exe_name, $exe_path)` 注册可执行文件映射。示例：
  ```php
  $router->addExePathMapping('python', '/usr/bin/python3');
  ```
  之后 CLI 命令 `python script.py` 将被路由到外部程序，路由器返回 `['python', '/usr/bin/python3']`，由调用器执行。

### 模块模式（路由扩展）

模块模式是一种替代路由策略，将代码组织成独立的模块。特别适用于大型应用，将功能分组为可复用、独立的单元。

**工作原理**：

- 当 `$app->mode === 'module'` 时，路由器使用不同的解析逻辑。
- 每个模块位于 `api_dir` 下的子目录中（例如 `modules/`）。
- 模块必须包含 `module.json` 文件，至少包含 `name`、`version`、`entry` 字段。
- 入口文件（默认为 `go.php`）必须在正确的命名空间下定义一个与入口文件名相同的类（不含扩展名）。例如，`go.php` 应在命名空间
  `modules\calculator` 下定义类 `go`。随后路由系统会调用命令中指定的方法（例如 `calculator/add` → `add`
  方法）。方法参数通过依赖注入自动解析。
- Web 路由：`/{模块名}/{方法}` 映射到模块的入口类和方法。
- CLI 路由：以模块目录开头的绝对路径或`/{模块名}/{方法}`（例如 `/modules/calculator/add` 或 `calculator/add`
  ）触发模块模式；其他绝对路径直接映射到完全限定的类/方法调用。

**示例**：

```php
// 启用模块模式
$ns->setMode('module')->setApiDir('modules')->go();

// 模块结构：modules/calculator/
// module.json: {"name":"calculator","version":"1.0.0","entry":"go.php"}
// go.php:
namespace modules\calculator;
class go {
    public function add(int $a, int $b): array {
        return ['result' => $a + $b];
    }
}

// Web 调用：/index.php?c=calculator/add&a=5&b=3
// CLI 调用：php index.php -c"calculator/add" -d'a=5&b=3'
```

所有现有特性（钩子、错误处理、日志、性能分析）都与模块模式无缝协作。

### 输入输出处理 (IOData 类)

**功能**：统一处理所有输入输出数据，提供一致的数据处理接口。

**主要方法**：

- `readCgi()` - 读取 Web 请求数据
- `readCli()` - 读取命令行参数
- `getInputData($keep_headers = false, $keep_cookies = false)` - 获取处理后的输入数据
- `output()` - 格式化输出数据

**支持格式**：

- 输入：JSON、XML、表单数据、查询字符串
- 输出：JSON、XML、纯文本、HTML

**示例**：

```php
$ioData = IOData::new();
$ioData->readHeaderKeys('Authorization');
$ioData->readCgi();
$inputData = $ioData->getInputData();
$ioData->src_output = ['success' => true];
$ioData->output();
```

### 安全防护 (Security 类)

**功能**：提供全面的安全防护功能，保障应用数据安全。

**主要方法**：

- `getApiResource($class_name, $method_name, $class_args = [], $filter = null)` - 验证 API 资源
- `antiXss($data)` - XSS 攻击防护

**安全特性**：

- API 资源安全验证
- XSS 自动过滤
- 框架核心类保护

**示例**：

```php
$security = Security::new();
$security->addXssSkipKeys('html_content');
$safeData = $security->antiXss($_POST);
```

### 反射管理 (Reflect 类)

**功能**：提供高效的反射信息缓存管理，大幅提升反射操作性能。

**主要方法**：

- `getClass($class_name)` - 获取类反射信息
- `getMethod($class_name, $method_name)` - 获取方法反射信息
- `getMethods($class_name, $filter = null)` - 获取类所有方法

**性能优化**：

- 智能缓存反射对象
- 减少重复反射操作
- 批量获取支持

**示例**：

```php
$methods = Reflect::getMethods('App\Controller\UserController', \ReflectionMethod::IS_PUBLIC);
$method = Reflect::getMethod('UserService', 'createUser');
```

### 跨域处理 (CORS 类)

**功能**：处理跨域请求和安全配置，简化跨域资源共享设置。

**主要方法**：

- `addRule($allowed_origin, $allowed_headers = '', $exposed_headers = '')` - 添加跨域规则
- `checkPermission($is_https)` - 检查并处理跨域请求

**支持特性**：

- 多域名配置
- 自定义请求头
- 预检请求处理
- 安全验证

**示例**：

```php
$cors = CORS::new();
$cors->addRule('https://example.com', 'Authorization,Content-Type');
$cors->checkPermission($app->is_https);
```

### 方法调用器 (Caller 类)

**功能**：安全地执行 API 方法和外部程序，提供统一的调用接口。

**主要方法**：

- `runApiFn($cmd, $api_args, $anti_xss)` - 执行 API 方法调用
- `runProgram($cmd_pair, $cmd_argv = [], $cwd_path = '', $realtime_debug = false)` - 执行外部程序

**安全特性**：

- 自动 XSS 防护
- API 资源验证
- 异常安全处理

**示例**：

```php
$caller = Caller::new();
$result = $caller->runApiFn(['UserController', 'getProfile'], ['userId' => 123], true);
```

### 性能分析 (Profiler 类)

**功能**：代码性能监控和分析，帮助定位性能瓶颈。

**主要方法**：

- `setThresholds($memory_bytes, $time_milliseconds)` - 设置性能阈值
- `start($profile_name, $analyze_cli = false)` - 开始性能分析
- `end($profile_name, $force_save = false, $with_input_data = false, $log_file_name = 'profiler')` - 结束性能分析

**监控指标**：

- 执行时间
- 内存使用
- 调用次数
- 阈值警告

**示例**：

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100);
$profiler->start('db_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('db_query');
```

### 操作系统管理 (OSMgr 类)

**功能**：跨平台操作系统功能封装，提供统一的系统操作接口。

**主要方法**：

- `getIPv4()` - 获取本机 IPv4 地址
- `getIPv6()` - 获取本机 IPv6 地址
- `getHwHash()` - 获取硬件哈希标识
- `getPhpPath()` - 获取 PHP 可执行路径
- `buildCmd($command)` - 构建系统命令

**跨平台支持**：

- Windows (WINNT)
- Linux
- macOS (Darwin)

**示例**：

```php
$osMgr = OSMgr::new();
$ipv4Addresses = $osMgr->getIPv4();
$command = $osMgr->inBackground(true)->buildCmd('php worker.php');
exec($command);
```

### 进程管理器 (ProcMgr 类)

**功能**：多进程管理和进程间通信，支持高并发任务处理。

**主要方法**：

- `command($command)` - 设置要执行的命令
- `runMP($run_proc = 8, $max_executions = 2000)` - 运行多进程池
- `putJob($job_argv, $stdout_callback = null, $stderr_callback = null)` - 提交任务
- `awaitJobs()` - 等待所有任务完成

**进程特性**：

- 进程池负载均衡
- 任务队列管理
- 进程间通信
- 错误恢复机制

**示例**：

```php
$procMgr = ProcMgr::new();
$procMgr->command(['php', 'worker.php'])->runMP(4, 1000);
$procMgr->putJob(json_encode(['task_id' => 1]));
$procMgr->awaitJobs();
```

### 纤程管理器 (FiberMgr 类)

**功能**：PHP 纤程（协程）管理，实现轻量级并发处理。

**主要方法**：

- `await($callable, $arguments = [])` - 创建并启动纤程
- `async($callable, $arguments = [], $callback = null)` - 添加异步任务
- `commit()` - 提交执行所有异步任务

**纤程特性**：

- 轻量级并发
- 协作式调度
- 低内存开销
- 单线程并发

**示例**：

```php
$fiberMgr = FiberMgr::new();
$fiberMgr->async(function($userId) { return fetchUserData($userId); }, ['userId' => 123]);
$fiberMgr->commit();
```

### 套接字管理器 (SocketMgr 类)

**功能**：Socket 通信和 WebSocket 支持，构建实时通信应用。

**主要方法**：

- `listenTo($address, $websocket = false)` - 启动服务器监听
- `connectTo($address)` - 连接到服务器
- `setDebugMode($debug_mode)` - 设置调试模式
- `onConnect($callback)` - 连接事件监听器
- `onMessage($callback)` - 消息事件监听器
- `sendMessage($socket_id, $message)` - 发送消息

**协议支持**：

- TCP
- UDP
- WebSocket
- SSL/TLS

**示例**：

```php
$socketMgr = SocketMgr::new();
$socketMgr->setDebugMode(true)
    ->onConnect(function($socketId) { echo "客户端连接\n"; })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "已收到: $message");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 🔧 高级功能

### 进程管理 (ProcMgr 类)

多进程任务处理，适用于批量数据处理和高并发场景。

```php
$procMgr = ProcMgr::new()->command(['php', 'worker.php'])->runMP(4);
$procMgr->putJob(json_encode(['task_id' => 1]));
$procMgr->awaitJobs();
```

### WebSocket 通信 (SocketMgr 类)

实时通信支持，轻松构建聊天应用、实时通知等场景。

```php
$socketMgr = SocketMgr::new()
    ->onConnect(function($socketId) { echo "客户端 $socketId 连接\n"; })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "已收到: $message");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

### 性能分析 (Profiler 类)

代码性能监控，帮助优化应用性能表现。

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100);
$profiler->start('db_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('db_query');
```

### 中间件系统

通过钩子系统实现灵活的中间件机制，处理认证、日志等横切关注点。

```php
class AuthMiddleware {
    public function checkToken($token): bool {
        return $this->validateToken($token);
    }
}
$ns->assignHook([new AuthMiddleware(), 'checkToken'], '/api/', '/api/auth/login');
```

### 自定义路由

扩展默认路由机制，实现 RESTful 路由等高级路由功能。

```php
$router->addCgiRouter(function($path) {
    if (preg_match('/^api\/(v[0-9]+)\/([a-z]+)\/([0-9]+)$/', $path, $matches)) {
        return ["Api\\{$matches[1]}\\".ucfirst($matches[2])."Controller", 'show'];
    }
    return [];
});
```

### 实时通信应用

构建完整的实时聊天服务器，展示 SocketMgr 的强大功能。

```php
$socketMgr = SocketMgr::new();
$users = [];
$socketMgr->onConnect(function($id) use (&$users) { $users[$id] = $id; })
    ->onMessage(function($id, $msg) use ($socketMgr, &$users) {
        foreach ($users as $uid) $socketMgr->sendMessage($uid, "用户 $id 说: $msg");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 📁 项目结构建议

```
project/
├── api/                   # API 接口层（API 模式）
├── modules/               # 模块目录（模块模式）
├── app/                   # 应用层
│   ├── Service/           # 业务服务
│   ├── Model/             # 数据模型
│   └── Middleware/        # 中间件
├── config/                # 配置文件
├── logs/                  # 日志
├── public/                # Web 入口
│   └── index.php
└── vendor/                # 依赖包
```

### 环境配置管理

通过环境变量管理不同环境的配置，实现配置与代码分离。

```php
// config/environment.php
return [
    'development' => ['debug' => true],
    'production'  => ['debug' => false]
];
$env = $_SERVER['APP_ENV'] ?? 'development';
$config = require __DIR__ . '/../config/environment.php';
$ns->setDebugMode($config[$env]['debug']);
```

### 错误监控和日志

实现自定义错误处理器，提供更友好的错误信息和日志记录。

```php
class CustomErrorHandler {
    public function handle(App $app, IOData $ioData, Throwable $e, bool $report): void {
        // 记录错误日志
    }
}
$error->addErrorHandler([new CustomErrorHandler(), 'handle']);
```

---

## ❓ 常见问题

### Q: 如何升级框架？

**A:** 直接替换框架文件即可，Nervsys 采用无状态设计，升级简单安全。建议升级前备份项目代码。

### Q: 支持 PHP 8.2/8.3/8.4/8.5 吗？

**A:** 完全支持 PHP 8.1+ 的所有版本，包括最新的 PHP 8.5。

### Q: 如何处理数据库操作？

**A:** 框架内置两种数据库处理方案：`Ext\libPDO`（多数据库通用）和 `Ext\libMySQL`（MySQL 专用）。

### Q: 如何集成第三方库（如 Composer 包）？

**A:** 使用 `addAutoloadPath()` 方法添加 Composer 的 vendor 目录即可。

### Q: 生产环境如何配置？

**A:** 关闭调试模式、配置错误处理器、设置正确的文件权限、启用 HTTPS 和 CORS。

### Q: 框架性能如何？

**A:** Nervsys 以高性能为设计目标：极简核心、快速启动、内存友好、高并发支持。

### Q: 新手应该如何开始？

**A:** 按照快速开始指南操作。从 API 模式开始，然后根据需要探索模块模式和高级功能。

---

## 🤝 贡献指南

我们欢迎并感谢所有形式的贡献！

### 贡献流程

1. Fork 仓库
2. 创建分支：`git checkout -b feature/your-feature-name`
3. 提交更改
4. 推送分支
5. 创建 Pull Request

### 开发规范

- 遵循 PSR 编码标准
- 添加适当的注释和文档说明
- 确保代码兼容 PHP 8.1+

---

## 📄 许可证

Nervsys 采用 **Apache License 2.0** 开源协议。  
查看完整的许可证内容：[LICENSE.md](LICENSE.md)

**主要条款**：

- ✅ 允许商业使用、修改、分发
- ✅ 要求保留版权和许可证声明
- ⚠️ 不提供专利授权
- ⚠️ 不承担用户使用风险

---

## 📞 支持与反馈

### 获取帮助

- 📚 **文档**：本 README 是主要文档
- 🐛 **问题反馈**：[GitHub Issues](https://github.com/Jerry-Shaw/Nervsys/issues)
- 💬 **技术讨论**：欢迎在 Issues 中发起技术讨论

### 联系作者

- 📧 **邮箱**：jerry-shaw@live.com, 27206617@qq.com, 904428723@qq.com
- ⭐ **Star 支持**：如果您觉得框架不错，欢迎给个 Star！

### 版本更新

- 🔔 **关注 Releases**：获取最新版本和更新说明

---

**Nervsys** - 像神经系统一样智能处理数据流，构建高效的 PHP 应用。