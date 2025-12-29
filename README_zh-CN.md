# Nervsys - 极简 PHP 框架

[![release](https://img.shields.io/badge/release-8.2.8-blue?style=flat-square)](https://github.com/Jerry-Shaw/Nervsys/releases)
[![php](https://img.shields.io/badge/php-8.1+-brightgreen?style=flat-square)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-Apache%202.0-blue?style=flat-square)](LICENSE.md)

### README: [English](README.md) | [简体中文](README_zh-CN.md)

## 概述

**Nervsys**（源自 "Nerve System"）是一个极简、高性能的 PHP 框架，专为现代化 Web 应用和 API 开发设计。框架设计理念借鉴神经系统，旨在像神经细胞一样灵活处理数据流，帮助开发者构建基于纯数据调用的智能应用系统。

## 🚀 核心特性

- **轻量级设计**：核心精简，无冗余依赖
- **智能路由**：自动参数映射，减少重复代码
- **双模支持**：同时支持 Web (CGI) 和命令行 (CLI) 模式
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
   项目根目录/
   ├── api/           # API 接口类
   │   └── User.php   # 示例API
   ├── app/           # 应用类
   │   ├── Service/   # 业务服务
   │   └── Middleware/# 中间件
   ├── www/           # Web入口目录
   │   └── index.php  # 主入口文件
   └── logs/          # 日志目录（自动创建）
   ```

3. **配置入口文件** (`www/index.php`)

   ```php
   <?php
   require __DIR__ . '/../Nervsys/NS.php';
   
   $ns = new Nervsys\NS();
   
   // 可选配置（按需设置）
   $ns->setApiDir('api')                    // API目录
      ->setDebugMode(true)                  // 调试模式（生产环境关闭）
      ->setContentType('application/json')  // 响应类型
      ->assignHook([\app\middleware\Auth::new(), 'check'], '/api/')
      ->addAutoloadPath(__DIR__ . '/../vendor');
   
   $ns->go();
   ```

---

## 📖 使用指南

### 创建第一个 API

1. **定义 API 类** (`api/User.php`)

   ```php
   <?php
   namespace api;
   
   class User
   {
       public function login(string $username, string $password): array
       {
           if ($username === 'admin' && $password === '123456') {
               return [
                   'success' => true, 
                   'message' => '登录成功',
                   'token' => md5($username . time())
               ];
           }
           
           return ['success' => false, 'message' => '用户名或密码错误'];
       }
       
       public function getProfile(int $userId, string $fields = 'basic'): array
       {
           $userData = [
               'id' => $userId,
               'name' => '张三',
               'email' => 'zhangsan@example.com',
               'age' => 28
           ];
           
           return ['success' => true, 'data' => $userData];
       }
   }
   ```

2. **调用 API**

   **Web 方式：**
   ```http
   GET /index.php?c=User/login&username=admin&password=123456
   
   POST /index.php
   Content-Type: application/json
   
   {
       "c": "User/login",
       "username": "admin",
       "password": "123456"
   }
   ```

   **CLI 方式：**
   ```bash
   # 参数调用
   php index.php -c"user/login" -d'username=admin&password=123456'
   
   # JSON 数据
   php index.php -c"user/login" -d'{"username":"admin","password":"123456"}'
   
   # 指定输出格式
   php index.php -c"user/getProfile" -d'userId=123' -r"json"
   ```

---

## 🎯 快速上手指引

Nervsys 采用 **"约定优于配置"** 的设计理念，让您能够跳过繁琐的配置步骤，直接基于智能的默认设置开始高效开发。

### 📌 核心原则

1. **配置集中化**  
   所有框架配置功能都集成在 `System Trait` 中，您无需在不同文件中寻找配置选项，一站式搞定所有设置。

2. **初始化极简**  
   在入口文件中，通过 `new NS()` 创建实例后，只需链式调用您需要的配置方法，即可快速完成框架初始化。

3. **按需学习，渐进深入**
   - **初学者**：专注于编写您的业务 API，无需关心底层实现
   - **进阶用户**：需要时再了解路由、中间件等特定模块
   - **高级开发**：按需探索并发处理、Socket通信等高级功能

### 🛠️ 扩展支持

- **内置扩展类**：常用功能增强类已预置在 `Ext` 目录中，开箱即用
- **扩展文档**：详细的扩展类使用手册正在积极构建中，敬请期待

---

💡 **开发心法**：先让它跑起来，再按需逐步优化调整。Nervsys 的设计理念就是让您快速起步，平滑过渡到高级功能。

---

## 🏗️ 核心组件

### 系统配置 (System Trait)
**功能**：提供完整的框架配置接口，包含了所有运行时配置选项，是框架的核心控制中枢。

**主要配置方法**：

**路径与目录配置**：
- `setRootPath($path)` - 设置应用根目录
- `setApiDir($dir_name)` - 设置API目录
- `addAutoloadPath($path, $prepend = false)` - 添加自动加载路径

**运行环境配置**：
- `setTimezone($timezone)` - 设置时区
- `setDebugMode($debug_mode)` - 设置调试模式
- `setLocale($locale)` - 设置区域语言

**安全与跨域配置**：
- `addCorsRule($allow_origin, $allow_headers = '', $expose_headers = '')` - 添加CORS规则
- `addXssSkipKeys(...$keys)` - 设置XSS过滤跳过键

**钩子系统**：
- `assignHook($hook_fn, $target_path, ...$exclude_path)` - 注册钩子函数

**错误处理**：
- `addErrorHandler($handler)` - 添加错误处理器

**数据I/O配置**：
- `readHeaderKeys(...$keys)` - 指定读取的HTTP头部键
- `readCookieKeys(...$keys)` - 指定读取的Cookie键
- `setContentType($content_type)` - 设置响应内容类型

**性能监控**：
- `setProfilerThresholds($memory_bytes, $time_milliseconds)` - 设置性能分析阈值

**示例**：
```php
$ns = new Nervsys\NS('/var/www/myapp', 'api/v2');
$ns->setRootPath('/var/www/app')
    ->setApiDir('api/v1')
    ->setDebugMode($_ENV['APP_DEBUG'] ?? false)
    ->addCorsRule('https://example.com', 'Authorization,Content-Type')
    ->assignHook([$auth, 'checkToken'], '/api/', '/api/auth/login')
    ->addAutoloadPath(__DIR__ . '/vendor')
    ->go();
```

### 应用环境 (App 类)
**功能**：管理应用运行环境和配置信息，提供全局访问点。

**主要方法**：
- `setRoot($root_path)` - 设置根目录
- `setApiDir($api_dir)` - 设置API目录
- `setLocale($locale)` - 设置区域
- `setDebugMode($core_debug)` - 设置调试模式

**环境信息属性**：
- `$client_ip` - 客户端IP地址
- `$user_lang` - 用户语言偏好
- `$user_agent` - 用户代理
- `$is_cli` - 是否CLI模式
- `$is_https` - 是否HTTPS协议

**示例**：
```php
$app = App::new();
echo "客户端IP: " . $app->client_ip;
echo "运行模式: " . ($app->is_cli ? 'CLI' : 'Web');
```

### 错误处理 (Error 类)
**功能**：统一的错误和异常处理系统，提供优雅的错误恢复机制。

**主要方法**：
- `saveLog($app, $log_file, $log_content)` - 保存日志文件
- `formatLog($err_lv, $message, $context = [])` - 格式化日志内容
- `exceptionHandler($throwable, $report_error = true, $stop_on_error = true)` - 异常处理器

**错误级别**：
- **error**：致命错误（E_ERROR, E_PARSE等）
- **warning**：警告错误（E_WARNING, E_USER_WARNING等）
- **notice**：通知信息（E_NOTICE, E_DEPRECATED等）

**示例**：
```php
$error = Error::new();

// 添加自定义错误处理器
$error->addErrorHandler([$monitor, 'trackError']);
$error->addErrorHandler([$notifier, 'sendAlert']);

// 记录自定义日志
$logContent = $error->formatLog('warning', 'API调用频率过高', [
    'user_id' => 123,
    'endpoint' => '/api/user/profile',
    'count' => 100
]);
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
- 流程中断控制 - 钩子返回非true时，请求流程会被中断

**示例**：
```php
$hook = Hook::new();

// 注册认证钩子
$hook->assign([$auth, 'checkToken'], '/api/', '/api/auth/login');

// 注册日志钩子
$hook->assign([$logger, 'accessLog'], '/');

// 执行钩子
if ($hook->run('/api/user/getInfo')) {
    // 钩子检查通过，继续处理
}
```

### 对象工厂 (Factory 类)
**功能**：提供智能对象创建和依赖注入，简化对象生命周期管理。

**主要方法**：
- `new()` - 创建当前类实例
- `getObj($class_name, $class_args = [], $user_passed = false)` - 获取对象实例
- `buildArgs($param_reflects, $data_package)` - 构建参数数组

**特性**：
- 自动依赖注入 - 自动解析并注入构造函数依赖
- 对象复用（单例模式） - 智能管理对象实例，提升性能
- 参数自动映射 - 根据参数名称自动匹配输入数据
- 类型安全转换 - 自动进行类型转换和验证

**示例**：
```php
class UserService extends \Nervsys\Core\Factory {
    public function __construct(\Nervsys\Ext\libMySQL $db, int $user_id) {
        // 自动注入 $db 实例
    }
}

// 自动注入 $db 实例，并将 $user_id = 1 传入到构造函数
$service = UserService::new(['user_id' => 1]);
```

### 路由系统 (Router 类)
**功能**：处理请求路由解析，同时支持Web和命令行双模式，提供高度灵活的路由机制。

**主要方法**：
- `parseCgi($c)` - 解析Web请求路由
- `parseCli($c)` - 解析命令行请求路由
- `addCgiRouter($router)` - 添加Web路由处理器
- `addExePathMapping($exe_name, $exe_path)` - 添加可执行文件映射

**路由特性**：
- 路由栈机制（优先级处理） - 支持多级路由处理器
- 自定义路由处理器 - 完全掌控路由逻辑
- 可执行文件映射 - 便捷集成外部工具
- 路径规范化 - 统一处理路径格式

**示例**：
```php
$router = Router::new();

// 自定义路由处理器
$router->addCgiRouter(function($path) {
    if (str_starts_with($path, 'v2/')) {
        return ['Api\\V2\\' . str_replace('/', '\\', substr($path, 3)), 'handle'];
    }
    return [];
});

// 添加CLI命令映射
$router->addExePathMapping('python', '/usr/bin/python3');

// 解析路由
$webRoute = $router->parseCgi('user/profile/getInfo'); // ['api\\user\\profile', 'getInfo']
$cliRoute = $router->parseCli('python script.py');     // ['python', '/usr/bin/python3']
```

### 输入输出处理 (IOData 类)
**功能**：统一处理所有输入输出数据，提供一致的数据处理接口。

**主要方法**：
- `readCgi()` - 读取Web请求数据
- `readCli()` - 读取命令行参数
- `getInputData($keep_headers = false, $keep_cookies = false)` - 获取处理后的输入数据
- `output()` - 格式化输出数据

**支持格式**：
- 输入：JSON、XML、表单数据、查询字符串
- 输出：JSON、XML、纯文本、HTML

**示例**：
```php
$ioData = IOData::new();

// 配置数据读取
$ioData->readHeaderKeys('Authorization', 'X-API-Version');
$ioData->readCookieKeys('session_token');

// 读取Web请求
$ioData->readCgi();

// 获取处理后的数据
$inputData = $ioData->getInputData(true, true);

// 设置输出
$ioData->src_output = ['success' => true, 'data' => $result];
$ioData->setContentType('application/json');

// 输出响应
$ioData->output();
```

### 安全防护 (Security 类)
**功能**：提供全面的安全防护功能，保障应用数据安全。

**主要方法**：
- `getApiResource($class_name, $method_name, $class_args = [], $filter = null)` - 验证API资源
- `antiXss($data)` - XSS攻击防护

**安全特性**：
- API资源安全验证 - 确保只有合法的API能够被调用
- XSS自动过滤 - 智能防护跨站脚本攻击
- 框架核心类保护 - 防止框架核心组件被恶意调用

**示例**：
```php
$security = Security::new();

// 配置XSS过滤跳过键
$security->addXssSkipKeys('html_content', 'raw_data', 'code_block');

// 执行XSS防护
$safeData = $security->antiXss($_POST);
```

### 反射管理 (Reflect 类)
**功能**：提供高效的反射信息缓存管理，大幅提升反射操作性能。

**主要方法**：
- `getClass($class_name)` - 获取类反射信息
- `getMethod($class_name, $method_name)` - 获取方法反射信息
- `getMethods($class_name, $filter = null)` - 获取类所有方法

**性能优化**：
- 智能缓存反射对象 - 避免重复反射操作开销
- 减少重复反射操作 - 提升框架整体性能
- 支持批量获取 - 一次性获取多个反射信息

**示例**：
```php
// 获取类方法信息
$methods = Reflect::getMethods('App\Controller\UserController', \ReflectionMethod::IS_PUBLIC);

// 获取参数信息
$method = Reflect::getMethod('UserService', 'createUser');
$params = $method->getParameters();

foreach ($params as $param) {
    $info = Reflect::getParameterInfo($param);
    // $info 包含名称、类型、默认值等信息
}
```

### 跨域处理 (CORS 类)
**功能**：处理跨域请求和安全配置，简化跨域资源共享设置。

**主要方法**：
- `addRule($allowed_origin, $allowed_headers = '', $exposed_headers = '')` - 添加跨域规则
- `checkPermission($is_https)` - 检查并处理跨域请求

**支持特性**：
- 多域名配置 - 支持多个源站访问
- 自定义请求头 - 灵活控制请求头权限
- 预检请求处理 - 自动处理OPTIONS预检请求
- 安全验证 - 确保跨域请求的安全性

**示例**：
```php
$cors = CORS::new();
$cors->addRule('https://example.com', 'Authorization,Content-Type,X-API-Key')
    ->addRule('http://localhost:3000', 'Content-Type')
    ->addRule('*'); // 允许所有来源（谨慎使用）

// 在请求处理前调用
$cors->checkPermission($app->is_https);
```

### 方法调用器 (Caller 类)
**功能**：安全地执行API方法和外部程序，提供统一的调用接口。

**主要方法**：
- `runApiFn($cmd, $api_args, $anti_xss)` - 执行API方法调用
- `runProgram($cmd_pair, $cmd_argv = [], $cwd_path = '', $realtime_debug = false)` - 执行外部程序

**安全特性**：
- 自动XSS防护 - 内置安全防护机制
- API资源验证 - 确保调用的合法性
- 异常安全处理 - 优雅处理调用异常

**示例**：
```php
$caller = Caller::new();

// 执行API方法
$result = $caller->runApiFn(
    ['App\Controller\UserController', 'getProfile'],
    ['userId' => 123, 'fields' => 'all'],
    true // 启用XSS防护
);

// 执行系统命令
$output = $caller->runProgram(
    ['ls', '/usr/bin/ls'],
    ['-la', '/var/log'],
    '/tmp',
    false
);
```

### 性能分析 (Profiler 类)
**功能**：代码性能监控和分析，帮助定位性能瓶颈。

**主要方法**：
- `setThresholds($memory_bytes, $time_milliseconds)` - 设置性能阈值
- `start($profile_name, $analyze_cli = false)` - 开始性能分析
- `end($profile_name, $force_save = false, $with_input_data = false, $log_file_name = 'profiler')` - 结束性能分析

**监控指标**：
- 执行时间 - 精确到毫秒的执行时间监控
- 内存使用 - 实时内存使用情况跟踪
- 调用次数 - 统计代码块调用频率
- 超标警告 - 自动发现性能瓶颈

**示例**：
```php
$profiler = Profiler::new();

// 设置阈值：内存1MB，时间100ms
$profiler->setThresholds(1024 * 1024, 100);

// 监控数据库查询
$profiler->start('database_query');
$result = $db->query('SELECT * FROM users WHERE active = 1');
$profiler->end('database_query');

// 如果超过阈值，会自动记录详细日志
// 日志内容：时间、内存、参数等信息，便于性能调优
```

### 操作系统管理 (OSMgr 类)
**功能**：跨平台操作系统功能封装，提供统一的系统操作接口。

**主要方法**：
- `getIPv4()` - 获取本机IPv4地址
- `getIPv6()` - 获取本机IPv6地址
- `getHwHash()` - 获取硬件哈希标识
- `getPhpPath()` - 获取PHP可执行路径
- `buildCmd($command)` - 构建系统命令

**跨平台支持**：
- Windows (WINNT)
- Linux
- macOS (Darwin)

**示例**：
```php
$osMgr = OSMgr::new();

// 获取系统信息
$ipv4Addresses = $osMgr->getIPv4();
$hardwareHash = $osMgr->getHwHash();
$phpPath = $osMgr->getPhpPath();

// 构建后台命令
$command = $osMgr->inBackground(true)->buildCmd('php worker.php');

// 执行系统命令
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
- 进程池负载均衡 - 智能分配任务到空闲进程
- 任务队列管理 - 有序处理大量任务
- 进程间通信 - 支持进程间数据交换
- 错误恢复机制 - 自动处理进程异常

**示例**：
```php
$procMgr = ProcMgr::new();

// 启动4个worker进程
$procMgr->command(['php', 'worker.php'])
    ->runMP(4, 1000);

// 批量提交任务
for ($i = 0; $i < 100; $i++) {
    $procMgr->putJob(
        json_encode(['task_id' => $i, 'data' => 'task_' . $i]),
        function($output) use ($i) {
            echo "任务 {$i} 完成: {$output}\n";
        }
    );
}

// 等待所有任务完成
$procMgr->awaitJobs();
```

### 纤程管理器 (FiberMgr 类)
**功能**：PHP纤程（协程）管理，实现轻量级并发处理。

**主要方法**：
- `await($callable, $arguments = [])` - 创建并启动纤程
- `async($callable, $arguments = [], $callback = null)` - 添加异步任务
- `commit()` - 提交执行所有异步任务

**纤程特性**：
- 轻量级并发 - 极低的内存开销
- 协作式调度 - 纤程主动让出执行权
- 低内存开销 - 相比线程和进程更加轻量
- 单线程并发 - 在单线程中实现并发效果

**示例**：
```php
$fiberMgr = FiberMgr::new();

// 添加异步任务
$fiberMgr->async(
    function($userId) {
        Fiber::suspend(); // 挂起纤程
        return fetchUserData($userId);
    },
    ['userId' => 123],
    function($result) {
        echo "用户数据: " . json_encode($result) . "\n";
    }
);

// 提交执行所有任务
$fiberMgr->commit();
```

### 套接字管理器 (SocketMgr 类)
**功能**：Socket通信和WebSocket支持，构建实时通信应用。

**主要方法**：
- `listenTo($address, $websocket = false)` - 启动服务器监听
- `connectTo($address)` - 连接到服务器
- `setDebugMode($debug_mode)` - 设置调试模式
- `onConnect($callback)` - 连接事件监听器
- `onMessage($callback)` - 消息事件监听器
- `sendMessage($socket_id, $message)` - 发送消息

**协议支持**：
- TCP - 可靠的字节流传输
- UDP - 无连接的数据报传输
- WebSocket - 全双工通信协议
- SSL/TLS - 安全加密传输

**示例**：
```php
$socketMgr = SocketMgr::new();

// 配置WebSocket服务器
$socketMgr->setDebugMode(true)
    ->onConnect(function($socketId) {
        echo "客户端 {$socketId} 连接\n";
    })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        echo "收到消息: {$message}\n";
        // 处理消息并回复
        $socketMgr->sendMessage($socketId, "已收到: {$message}");
    });

// 启动WebSocket服务器
$socketMgr->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 🔧 高级功能

### 进程管理 (ProcMgr 类)
多进程任务处理，适用于批量数据处理和高并发场景。

```php
$procMgr = ProcMgr::new()
    ->command(['php', 'worker.php'])
    ->runMP(4); // 启动4个worker进程

// 提交任务
for ($i = 0; $i < 100; $i++) {
    $procMgr->putJob(
        json_encode(['task_id' => $i]),
        function($output) use ($i) {
            echo "任务 {$i} 完成: {$output}\n";
        }
    );
}

$procMgr->awaitJobs();
```

### WebSocket 通信 (SocketMgr 类)
实时通信支持，轻松构建聊天应用、实时通知等场景。

```php
$socketMgr = SocketMgr::new()
    ->setDebugMode(true)
    ->onConnect(function($socketId) {
        echo "客户端 {$socketId} 连接\n";
    })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "已收到: {$message}");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

### 性能分析 (Profiler 类)
代码性能监控，帮助优化应用性能表现。

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100); // 1MB内存，100ms时间

$profiler->start('database_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('database_query'); // 自动记录超阈值日志
```

### 中间件系统
通过钩子系统实现灵活的中间件机制，处理认证、日志等横切关注点。

```php
// 创建认证中间件
class AuthMiddleware
{
    public function checkToken($token): bool
    {
        return $this->validateToken($token);
    }
}

// 注册中间件
$ns->assignHook([new AuthMiddleware(), 'checkToken'], '/api/', '/api/auth/login');
```

### 自定义路由
扩展默认路由机制，实现RESTful路由等高级路由功能。

```php
// 自定义路由处理器
$router->addCgiRouter(function($path) {
    if (preg_match('/^api\/(v[0-9]+)\/([a-z]+)\/([0-9]+)$/', $path, $matches)) {
        $version = $matches[1];
        $resource = ucfirst($matches[2]);
        $id = $matches[3];
        
        return ["Api\\{$version}\\{$resource}Controller", 'show'];
    }
    
    return [];
});
```

### 实时通信应用
构建完整的实时聊天服务器，展示SocketMgr的强大功能。

```php
// 实时聊天服务器
$socketMgr = SocketMgr::new();
$connectedUsers = [];

$socketMgr->onConnect(function($socketId) use (&$connectedUsers) {
    $connectedUsers[$socketId] = ['id' => $socketId, 'connected_at' => time()];
});

$socketMgr->onMessage(function($socketId, $message) use ($socketMgr, &$connectedUsers) {
    $data = json_decode($message, true);
    
    if ($data['type'] === 'chat') {
        foreach ($connectedUsers as $user) {
            $socketMgr->sendMessage($user['id'], json_encode([
                'type' => 'message',
                'from' => $socketId,
                'content' => $data['content'],
                'time' => date('H:i:s')
            ]));
        }
    }
});

$socketMgr->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 📁 项目结构建议

```
project/
├── api/                   # API接口层
│   ├── v1/                # 版本1
│   └── v2/                # 版本2
├── app/                   # 应用层
│   ├── Service/           # 业务服务
│   ├── Model/             # 数据模型
│   └── Middleware/        # 中间件
├── config/                # 配置文件
├── logs/                  # 日志
├── public/                # Web入口
│   └── index.php
└── vendor/                # 依赖包
```

### 环境配置管理
通过环境变量管理不同环境的配置，实现配置与代码分离。

```php
// config/environment.php
return [
    'development' => [
        'debug' => true,
        'database' => [
            'host' => 'localhost',
            'name' => 'app_dev'
        ]
    ],
    'production' => [
        'debug' => false,
        'database' => [
            'host' => 'db.server.com',
            'name' => 'app_prod'
        ]
    ]
];

// 入口文件配置
$env = $_SERVER['APP_ENV'] ?? 'development';
$config = require __DIR__ . '/../config/environment.php';

$ns = new Nervsys\NS();
$ns->setDebugMode($config[$env]['debug']);
// ... 其他配置
```

### 错误监控和日志
实现自定义错误处理器，提供更友好的错误信息和日志记录。

```php
// 自定义错误处理器
class CustomErrorHandler
{
    public function handle(App $app, IOData $ioData, Throwable $e, bool $report): void
    {
        // 记录详细错误信息
        $this->logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request' => [
                'command' => $ioData->src_cmd,
                'input' => $ioData->src_input,
                'client_ip' => $app->client_ip
            ]
        ]);
        
        // 生产环境返回通用错误信息
        if (!$app->debug_mode) {
            $ioData->src_msg = [
                'code' => 500,
                'message' => '系统繁忙，请稍后重试',
                'request_id' => uniqid()
            ];
        }
    }
}

// 注册错误处理器
$error->addErrorHandler([new CustomErrorHandler(), 'handle']);
```

---

## ❓ 常见问题

### Q: 如何升级框架？
**A:** 直接替换框架文件即可，Nervsys 采用无状态设计，升级简单安全。建议升级前备份项目代码，以防万一。

### Q: 支持 PHP 8.2/8.3/8.4/8.5 吗？
**A:** 完全支持 PHP 8.1+ 的所有版本，包括最新的 PHP 8.5。框架持续跟进PHP最新特性。

### Q: 如何处理数据库操作？
**A:** 框架内置两种数据库处理方案：
- **通用方案**：`Ext\libPDO` - 支持 MySQL、MSSQL、PostgreSQL、Oracle 等多种数据库，提供标准PDO接口
- **MySQL专用**：`Ext\libMySQL` - 提供更便捷的 MySQL 操作接口，封装常用操作

### Q: 如何集成第三方库（如Composer包）？
**A:** 使用 `addAutoloadPath()` 方法添加 Composer 的 vendor 目录即可：
```php
$ns->addAutoloadPath(__DIR__ . '/../vendor');
```

### Q: 生产环境如何配置？
**A:**
1. **调试模式**：务必关闭调试模式 (`setDebugMode(false)`)
2. **错误处理**：配置合适的错误处理器，避免敏感信息泄露
3. **文件权限**：设置正确的文件权限（logs目录需要可写）
4. **服务器配置**：按照普通的PHP项目部署即可，支持Nginx/Apache等常见服务器
5. **安全建议**：启用HTTPS，配置适当的CORS规则

### Q: 框架性能如何？
**A:** Nervsys 以高性能为设计目标：
- **极简核心**：核心文件仅几百KB，减少不必要的开销
- **快速启动**：优化的初始化流程，响应迅速
- **内存友好**：精简的代码结构和高效的内存管理
- **高并发支持**：内置多进程和纤程支持，适合高并发场景

### Q: 新手应该如何开始？
**A:** 建议按照以下路径学习：
1. **第一步（5分钟）**：配置入口文件，使用 `new NS()` 和基本设置
2. **第二步（15分钟）**：编写第一个 API 类并测试
3. **第三步（按需）**：根据项目需求添加路由、中间件等功能
4. **第四步（进阶）**：需要高级功能时查阅对应模块文档

**关键建议**：无需一开始就深入理解所有模块。Nervsys 的设计理念就是让您能够快速起步，在实际开发中逐步掌握更多功能。

---

## 🤝 贡献指南

我们欢迎并感谢所有形式的贡献！无论是报告bug、提出建议，还是提交代码，都能帮助Nervsys变得更好。

### 贡献流程
1. **Fork 仓库**：点击 GitHub 右上角的 Fork 按钮
2. **创建分支**：`git checkout -b feature/your-feature-name`
3. **提交更改**：`git commit -m 'Add some amazing feature'`
4. **推送分支**：`git push origin feature/your-feature-name`
5. **创建 PR**：在 GitHub 上创建 Pull Request，描述您的改动

### 开发规范
- 遵循 PSR 编码标准
- 添加适当的注释和文档说明
- 确保代码兼容 PHP 8.1+
- 如果涉及功能变更，请提供使用示例

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
- 📚 **文档**：本 README 是主要文档，包含完整的框架使用指南
- 🐛 **问题反馈**：[GitHub Issues](https://github.com/Jerry-Shaw/Nervsys/issues)
- 💬 **技术讨论**：欢迎在 Issues 中发起技术讨论

### 联系作者
- 📧 **邮箱**：jerry-shaw@live.com, 27206617@qq.com, 904428723@qq.com
- ⭐ **Star支持**：如果您觉得框架不错，欢迎给个 Star 支持项目发展！

### 版本更新
- 🔔 **关注 Releases**：获取最新版本和更新说明
- 📝 **更新日志**：重要变更会在 Releases 中详细说明