# Nervsys - Minimalist PHP Framework

[![release](https://img.shields.io/badge/release-8.3.0-blue?style=flat-square)](https://github.com/Jerry-Shaw/Nervsys/releases)
[![php](https://img.shields.io/badge/php-8.1+-brightgreen?style=flat-square)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-Apache%202.0-blue?style=flat-square)](LICENSE.md)

### README: [English](README.md) | [简体中文](README_zh-CN.md)

## Overview

**Nervsys** (derived from "Nerve System") is a minimalist, high-performance PHP framework designed for modern web
applications and API development. Inspired by the neural system, the framework aims to process data streams flexibly
like nerve cells, helping developers build intelligent application systems based on pure data calls.

## 🚀 Core Features

- **Lightweight Design**: Core components are streamlined without redundant dependencies
- **Intelligent Routing**: Automatic parameter mapping reduces repetitive code
- **Dual Mode Support**: Supports both Web (CGI) and Command Line (CLI) modes
- **Module Mode**: Organize code into self-contained modules, each with its own entry file and metadata – ideal for
  large, reusable applications
- **Comprehensive Security**: Built-in XSS protection, CORS support, API security validation
- **Performance Analysis**: Built-in performance monitoring and optimization tools
- **Concurrent Processing**: Supports fibers, multi-process, and Socket communication
- **Highly Extensible**: Modular design, easy to customize and extend

## 📋 Requirements

- PHP 8.1 or higher
- Support for both CLI and Web running modes
- Common web servers (Apache/Nginx/IIS) or direct CLI execution

---

## 🚦 Quick Start

### Installation

1. **Clone the framework source code**

```bash
git clone https://github.com/Jerry-Shaw/Nervsys.git
```

> 💡 **Tip**: One server only needs one set of Nervsys framework to run multiple projects

2. **Create project structure**

```
project_root/
├── api/           # API interface classes (default mode)
├── modules/       # Module directories (module mode)
├── app/           # Application classes
├── www/           # Web entry directory
│   └── index.php  # Main entry file
└── logs/          # Log directory (auto-created)
```

3. **Configure entry file** (`www/index.php`)

```php
<?php
require __DIR__ . '/../Nervsys/NS.php';

$ns = new Nervsys\NS();

// API mode (default)
$ns->setApiDir('api')
   ->setDebugMode(true)
   ->setContentType('application/json')
   ->go();
```

---

## 📖 Usage Guide

### Create Your First API (API Mode)

1. **Define API class** (`api/User.php`)

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

2. **Call the API**: `GET /index.php?c=User/login&username=admin&password=123`

### Create Your First Module (Module Mode)

Module mode is an alternative routing strategy. Each module is a self-contained directory.

1. **Enable module mode** in entry file:

```php
$ns->setMode('module')->setApiDir('modules')->go();
```

2. **Create module directory**: `modules/calculator/`

3. **Add `module.json`**:

```json
{
  "name": "calculator",
  "version": "1.0.0",
  "entry": "go.php"
}
```

4. **Create entry file** `modules/calculator/go.php`:

```php
<?php
namespace modules\calculator;
class go {
    public function add(int $a, int $b): array {
        return ['result' => $a + $b];
    }
}
```

5. **Call the module**: `GET /index.php?c=calculator/add&a=5&b=3`

> Module mode also works in CLI: `php index.php -c"calculator/add" -d'a=5&b=3'`

---

## 🎯 Quick Start Guide

Nervsys adopts the **"Convention Over Configuration"** design philosophy, allowing you to skip complex configuration
steps and start developing efficiently with intelligent default settings.

### 📌 Core Principles

1. **Centralized Configuration**  
   All framework configuration functions are integrated into `System Trait`. You don't need to search for configuration
   options in different files - one-stop configuration management.

2. **Simplified Initialization**  
   In the entry file, after creating an instance with `new NS()`, simply chain-call the configuration methods you need
   to quickly complete framework initialization.

3. **Learn as Needed, Progress Gradually**

- **Beginner**: Focus on writing your business APIs without worrying about underlying implementations
- **Intermediate user**: Learn about specific modules like routing and middleware when needed
- **Advanced developer**: Explore advanced features like concurrent processing and Socket communication as required

### 🛠️ Extension Support

- **Built-in extension classes**: Common functional enhancement classes are pre-built in the `Ext` directory, ready to
  use out of the box
- **Extension documentation**: Detailed extension class usage manuals are being actively developed - stay tuned!

---

💡 **Development Philosophy**: Get it running first, then optimize and adjust as needed. Nervsys is designed to help you
start quickly and smoothly transition to advanced features.

---

## 🏗️ Core Components

### System Configuration (System Trait)

**Function**: Provides a complete framework configuration interface containing all runtime configuration options. Serves
as the core control center of the framework.

**Main Configuration Methods**:

**Path & Directory Configuration**:

- `setRootPath($path)` - Set application root directory
- `setApiDir($dir_name)` - Set API directory (or module root directory in module mode)
- `addAutoloadPath($path, $prepend = false)` - Add autoload path

**Runtime Environment Configuration**:

- `setTimezone($timezone)` - Set timezone
- `setDebugMode($debug_mode)` - Set debug mode
- `setLocale($locale)` - Set locale/language

**Mode Configuration**:

- `setMode($mode)` - Switch between `api` (default) and `module` modes

**Security & CORS Configuration**:

- `addCorsRule($allow_origin, $allow_headers = '', $expose_headers = '')` - Add CORS rules
- `addXssSkipKeys(...$keys)` - Set XSS filter skip keys

**Hook System**:

- `assignHook($hook_fn, $target_path, ...$exclude_path)` - Register hook functions

**Error Handling**:

- `addErrorHandler($handler)` - Add error handler

**Data I/O Configuration**:

- `readHeaderKeys(...$keys)` - Specify HTTP header keys to read
- `readCookieKeys(...$keys)` - Specify cookie keys to read
- `setContentType($content_type)` - Set response content type

**Performance Monitoring**:

- `setProfilerThresholds($memory_bytes, $time_milliseconds)` - Set performance analysis thresholds

**Example**:

```php
$ns = new Nervsys\NS();
$ns->setRootPath('/var/www/app')
    ->setMode('module')
    ->setApiDir('modules')
    ->setDebugMode(true)
    ->go();
```

### Application Environment (App Class)

**Function**: Manages application runtime environment and configuration information, providing a global access point.

**Main Methods**:

- `setMode($mode)` - Set running mode (`api` or `module`)
- `setRoot($root_path)` - Set root directory
- `setApiDir($api_dir)` - Set API/module directory
- `setLocale($locale)` - Set locale
- `setDebugMode($core_debug)` - Set debug mode

**Environment Information Properties**:

- `$client_ip` - Client IP address
- `$user_lang` - User language preference
- `$user_agent` - User agent
- `$is_cli` - Whether in CLI mode
- `$is_https` - Whether using HTTPS protocol

**Example**:

```php
$app = App::new();
echo "Mode: " . $app->mode;
echo "Client IP: " . $app->client_ip;
```

### Error Handling (Error Class)

**Function**: Unified error and exception handling system providing graceful error recovery mechanisms.

**Main Methods**:

- `saveLog($app, $log_file, $log_content)` - Save log file
- `formatLog($err_lv, $message, $context = [])` - Format log content
- `exceptionHandler($throwable, $report_error = true, $stop_on_error = true)` - Exception handler

**Error Levels**:

- **error**: Fatal errors (E_ERROR, E_PARSE, etc.)
- **warning**: Warning errors (E_WARNING, E_USER_WARNING, etc.)
- **notice**: Notification information (E_NOTICE, E_DEPRECATED, etc.)

**Example**:

```php
$error = Error::new();
$error->addErrorHandler([$monitor, 'trackError']);
$logContent = $error->formatLog('warning', 'API call frequency too high');
$error->saveLog($app, 'security.log', $logContent);
```

### Hook System (Hook Class)

**Function**: Provides flexible middleware and pre-processing mechanisms, achieving separation of cross-cutting
concerns.

**Main Methods**:

- `assign($hook_fn, $target_path, ...$exclude_path)` - Register hook functions
- `run($full_cmd)` - Execute matched hooks

**Hook Features**:

- Path prefix matching - Precise control over hook scope
- Exclusion path support - Flexible exception configuration
- Automatic parameter injection - Simplifies parameter retrieval for hook functions
- Flow interruption control - Request flow is interrupted when hooks return non-true

**Example**:

```php
$hook = Hook::new();
$hook->assign([$auth, 'checkToken'], '/api/', '/api/auth/login');
if ($hook->run('/api/user/getInfo')) {
    // continue
}
```

### Object Factory (Factory Class)

**Function**: Provides intelligent object creation and dependency injection, simplifying object lifecycle management.

**Main Methods**:

- `new()` - Create current class instance
- `getObj($class_name, $class_args = [], $user_passed = false)` - Get object instance
- `buildArgs($param_reflects, $data_package)` - Build parameter array

**Features**:

- Automatic dependency injection
- Object reuse (Singleton pattern)
- Automatic parameter mapping
- Type-safe conversion

**Example**:

```php
class UserService extends \Nervsys\Core\Factory {
    public function __construct(\Nervsys\Ext\libMySQL $db, int $user_id) {}
}
$service = UserService::new(['user_id' => 1]);
```

### Routing System (Router Class)

**Function**: Handles request routing parsing, supporting both Web and command line dual modes with highly flexible
routing mechanisms.

**Main Methods**:

- `parseCgi($c)` - Parse Web request routes
- `parseCli($c)` - Parse command line request routes
- `addCgiRouter($router)` - Add Web route processor
- `addExePathMapping($exe_name, $exe_path)` - Add executable file mapping

**Routing Features**:

- Routing stack mechanism (priority processing)
- Custom route processors
- Executable file mapping
- Path normalization

**Example**:

```php
$router = Router::new();
$router->addCgiRouter(function($path) {
    if (str_starts_with($path, 'v2/')) {
        return ['Api\\V2\\' . str_replace('/', '\\', substr($path, 3)), 'handle'];
    }
    return [];
});
```

- **External program mapping**: For CLI commands that should execute external programs (e.g., `python script.py`), you
  can register executable mappings via `addExePathMapping($exe_name, $exe_path)`. Example:
  ```php
  $router->addExePathMapping('python', '/usr/bin/python3');
  ```
  Then a CLI command `python script.py` will be routed to the external program. The router returns
  `['python', '/usr/bin/python3']`, and the caller executes it.

### Module Mode (Router Extension)

Module mode is an alternative routing strategy that organizes code into self‑contained modules. It is especially useful
for large applications where functionality can be grouped into reusable, independent units.

**How it works**:

- When `$app->mode === 'module'`, the router uses a different parsing logic.
- Each module resides in its own subdirectory under the `api_dir` (e.g., `modules/`).
- A module must contain a `module.json` file with at least `name`, `version`, and `entry` fields.
- The entry file (default `go.php`) must define a class whose name matches the entry filename (without extension) under
  the correct namespace. For example, `go.php` should define class `go` under the namespace `modules\calculator`. The
  router will then invoke the method specified in the command (e.g., `calculator/add` → method `add`). Method parameters
  are resolved automatically via dependency injection.
- Routing in Web: `/{module_name}/{method}` maps to the module's entry class and method.
- Routing in CLI: Absolute paths that start with the module directory or `/{module_name}/{method}` (e.g.,
  `/modules/calculator/add` or `calculator/add`) trigger module mode; other absolute paths map directly to fully
  qualified class/method calls.

**Example**:

```php
// Enable module mode
$ns->setMode('module')->setApiDir('modules')->go();

// Module structure: modules/calculator/
// module.json: {"name":"calculator","version":"1.0.0","entry":"go.php"}
// go.php:
namespace modules\calculator;
class go {
    public function add(int $a, int $b): array {
        return ['result' => $a + $b];
    }
}

// Web call: /index.php?c=calculator/add&a=5&b=3
// CLI call: php index.php -c"calculator/add" -d'a=5&b=3'
```

All existing features (hooks, error handling, logging, profiling) work seamlessly with module mode.

### Input/Output Processing (IOData Class)

**Function**: Unified processing of all input/output data, providing consistent data processing interface.

**Main Methods**:

- `readCgi()` - Read Web request data
- `readCli()` - Read command line parameters
- `getInputData($keep_headers = false, $keep_cookies = false)` - Get processed input data
- `output()` - Format output data

**Supported Formats**:

- Input: JSON, XML, form data, query strings
- Output: JSON, XML, plain text, HTML

**Example**:

```php
$ioData = IOData::new();
$ioData->readHeaderKeys('Authorization');
$ioData->readCgi();
$inputData = $ioData->getInputData();
$ioData->src_output = ['success' => true];
$ioData->output();
```

### Security Protection (Security Class)

**Function**: Provides comprehensive security protection features to ensure application data security.

**Main Methods**:

- `getApiResource($class_name, $method_name, $class_args = [], $filter = null)` - Validate API resources
- `antiXss($data)` - XSS attack protection

**Security Features**:

- API resource security validation
- Automatic XSS filtering
- Framework core class protection

**Example**:

```php
$security = Security::new();
$security->addXssSkipKeys('html_content');
$safeData = $security->antiXss($_POST);
```

### Reflection Management (Reflect Class)

**Function**: Provides efficient reflection information cache management, significantly improving reflection operation
performance.

**Main Methods**:

- `getClass($class_name)` - Get class reflection information
- `getMethod($class_name, $method_name)` - Get method reflection information
- `getMethods($class_name, $filter = null)` - Get all class methods

**Performance Optimization**:

- Intelligent caching of reflection objects
- Reduced duplicate reflection operations
- Batch retrieval support

**Example**:

```php
$methods = Reflect::getMethods('App\Controller\UserController', \ReflectionMethod::IS_PUBLIC);
$method = Reflect::getMethod('UserService', 'createUser');
```

### Cross-Origin Processing (CORS Class)

**Function**: Handles cross-origin requests and security configuration, simplifying CORS setup.

**Main Methods**:

- `addRule($allowed_origin, $allowed_headers = '', $exposed_headers = '')` - Add cross-origin rules
- `checkPermission($is_https)` - Check and process cross-origin requests

**Supported Features**:

- Multiple domain configuration
- Custom request headers
- Preflight request handling
- Security validation

**Example**:

```php
$cors = CORS::new();
$cors->addRule('https://example.com', 'Authorization,Content-Type');
$cors->checkPermission($app->is_https);
```

### Method Caller (Caller Class)

**Function**: Safely executes API methods and external programs, providing unified calling interface.

**Main Methods**:

- `runApiFn($cmd, $api_args, $anti_xss)` - Execute API method calls
- `runProgram($cmd_pair, $cmd_argv = [], $cwd_path = '', $realtime_debug = false)` - Execute external programs

**Security Features**:

- Automatic XSS protection
- API resource validation
- Exception-safe handling

**Example**:

```php
$caller = Caller::new();
$result = $caller->runApiFn(['UserController', 'getProfile'], ['userId' => 123], true);
```

### Performance Analysis (Profiler Class)

**Function**: Code performance monitoring and analysis, helping identify performance bottlenecks.

**Main Methods**:

- `setThresholds($memory_bytes, $time_milliseconds)` - Set performance thresholds
- `start($profile_name, $analyze_cli = false)` - Start performance analysis
- `end($profile_name, $force_save = false, $with_input_data = false, $log_file_name = 'profiler')` - End performance
  analysis

**Monitoring Metrics**:

- Execution time
- Memory usage
- Call counts
- Threshold warnings

**Example**:

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100);
$profiler->start('db_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('db_query');
```

### Operating System Management (OSMgr Class)

**Function**: Cross-platform operating system functionality encapsulation, providing unified system operation interface.

**Main Methods**:

- `getIPv4()` - Get local IPv4 addresses
- `getIPv6()` - Get local IPv6 addresses
- `getHwHash()` - Get hardware hash identifier
- `getPhpPath()` - Get PHP executable path
- `buildCmd($command)` - Build system commands

**Cross-Platform Support**:

- Windows (WINNT)
- Linux
- macOS (Darwin)

**Example**:

```php
$osMgr = OSMgr::new();
$ipv4Addresses = $osMgr->getIPv4();
$command = $osMgr->inBackground(true)->buildCmd('php worker.php');
exec($command);
```

### Process Manager (ProcMgr Class)

**Function**: Multi-process management and inter-process communication, supporting high-concurrency task processing.

**Main Methods**:

- `command($command)` - Set command to execute
- `runMP($run_proc = 8, $max_executions = 2000)` - Run multi-process pool
- `putJob($job_argv, $stdout_callback = null, $stderr_callback = null)` - Submit tasks
- `awaitJobs()` - Wait for all tasks to complete

**Process Features**:

- Process pool load balancing
- Task queue management
- Inter-process communication
- Error recovery mechanism

**Example**:

```php
$procMgr = ProcMgr::new();
$procMgr->command(['php', 'worker.php'])->runMP(4, 1000);
$procMgr->putJob(json_encode(['task_id' => 1]));
$procMgr->awaitJobs();
```

### Fiber Manager (FiberMgr Class)

**Function**: PHP fiber (coroutine) management, implementing lightweight concurrent processing.

**Main Methods**:

- `await($callable, $arguments = [])` - Create and start fibers
- `async($callable, $arguments = [], $callback = null)` - Add asynchronous tasks
- `commit()` - Submit and execute all asynchronous tasks

**Fiber Features**:

- Lightweight concurrency
- Cooperative scheduling
- Low memory overhead
- Single-threaded concurrency

**Example**:

```php
$fiberMgr = FiberMgr::new();
$fiberMgr->async(function($userId) { return fetchUserData($userId); }, ['userId' => 123]);
$fiberMgr->commit();
```

### Socket Manager (SocketMgr Class)

**Function**: Socket communication and WebSocket support, building real-time communication applications.

**Main Methods**:

- `listenTo($address, $websocket = false)` - Start server listening
- `connectTo($address)` - Connect to server
- `setDebugMode($debug_mode)` - Set debug mode
- `onConnect($callback)` - Connection event listener
- `onMessage($callback)` - Message event listener
- `sendMessage($socket_id, $message)` - Send messages

**Protocol Support**:

- TCP
- UDP
- WebSocket
- SSL/TLS

**Example**:

```php
$socketMgr = SocketMgr::new();
$socketMgr->setDebugMode(true)
    ->onConnect(function($socketId) { echo "Client connected\n"; })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "Received: $message");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 🔧 Advanced Features

### Process Management (ProcMgr Class)

Multi-process task processing, suitable for batch data processing and high-concurrency scenarios.

```php
$procMgr = ProcMgr::new()->command(['php', 'worker.php'])->runMP(4);
$procMgr->putJob(json_encode(['task_id' => 1]));
$procMgr->awaitJobs();
```

### WebSocket Communication (SocketMgr Class)

Real-time communication support, easily building chat applications, real-time notifications, etc.

```php
$socketMgr = SocketMgr::new()
    ->onConnect(function($socketId) { echo "Client $socketId connected\n"; })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "Received: $message");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

### Performance Analysis (Profiler Class)

Code performance monitoring to help optimize application performance.

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100);
$profiler->start('db_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('db_query');
```

### Middleware System

Implement flexible middleware mechanism through hook system, handling cross-cutting concerns like authentication,
logging, etc.

```php
class AuthMiddleware {
    public function checkToken($token): bool {
        return $this->validateToken($token);
    }
}
$ns->assignHook([new AuthMiddleware(), 'checkToken'], '/api/', '/api/auth/login');
```

### Custom Routing

Extend default routing mechanism to implement advanced routing features like RESTful routing.

```php
$router->addCgiRouter(function($path) {
    if (preg_match('/^api\/(v[0-9]+)\/([a-z]+)\/([0-9]+)$/', $path, $matches)) {
        return ["Api\\{$matches[1]}\\".ucfirst($matches[2])."Controller", 'show'];
    }
    return [];
});
```

### Real-time Communication Application

Build complete real-time chat server, showcasing SocketMgr's powerful capabilities.

```php
$socketMgr = SocketMgr::new();
$users = [];
$socketMgr->onConnect(function($id) use (&$users) { $users[$id] = $id; })
    ->onMessage(function($id, $msg) use ($socketMgr, &$users) {
        foreach ($users as $uid) $socketMgr->sendMessage($uid, "User $id says: $msg");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 📁 Project Structure Recommendation

```
project/
├── api/                   # API interface layer (API mode)
├── modules/               # Module directories (module mode)
├── app/                   # Application layer
│   ├── Service/           # Business services
│   ├── Model/             # Data models
│   └── Middleware/        # Middleware
├── config/                # Configuration files
├── logs/                  # Logs
├── public/                # Web entry
│   └── index.php
└── vendor/                # Dependencies
```

### Environment Configuration Management

Manage different environment configurations through environment variables, achieving configuration-code separation.

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

### Error Monitoring and Logging

Implement custom error handlers for more friendly error information and comprehensive logging.

```php
class CustomErrorHandler {
    public function handle(App $app, IOData $ioData, Throwable $e, bool $report): void {
        // log error
    }
}
$error->addErrorHandler([new CustomErrorHandler(), 'handle']);
```

---

## ❓ Frequently Asked Questions

### Q: How to upgrade the framework?

**A:** Simply replace the framework files. Nervsys uses stateless design, making upgrades simple and safe.

### Q: Does it support PHP 8.2/8.3/8.4/8.5?

**A:** Fully supports all versions from PHP 8.1+, including the latest PHP 8.5.

### Q: How to handle database operations?

**A:** The framework includes two database processing solutions: `Ext\libPDO` (multi-database) and `Ext\libMySQL` (
MySQL-specific).

### Q: How to integrate third-party libraries (like Composer packages)?

**A:** Use the `addAutoloadPath()` method to add Composer's vendor directory.

### Q: How to configure for production environment?

**A:** Turn off debug mode, configure error handlers, set correct file permissions, enable HTTPS and CORS.

### Q: What about framework performance?

**A:** Nervsys is designed for high performance: minimalist core, fast startup, memory-friendly, and high concurrency
support.

### Q: How should beginners get started?

**A:** Follow the Quick Start guide. Start with API mode, then explore module mode and advanced features as needed.

---

## 🤝 Contribution Guidelines

We welcome and appreciate all forms of contributions!

### Contribution Process

1. Fork repository
2. Create branch: `git checkout -b feature/your-feature-name`
3. Commit changes
4. Push branch
5. Create Pull Request

### Development Standards

- Follow PSR coding standards
- Add appropriate comments and documentation
- Ensure code compatibility with PHP 8.1+

---

## 📄 License

Nervsys uses the **Apache License 2.0** open source license.  
View the complete license content: [LICENSE.md](LICENSE.md)

**Main Terms**:

- ✅ Allows commercial use, modification, distribution
- ✅ Requires preservation of copyright and license notices
- ⚠️ Does not provide patent grant
- ⚠️ No warranty of merchantability or fitness for purpose

---

## 📞 Support & Feedback

### Get Help

- 📚 **Documentation**: This README is the main documentation
- 🐛 **Issue reporting**: [GitHub Issues](https://github.com/Jerry-Shaw/Nervsys/issues)
- 💬 **Technical discussion**: Welcome to start technical discussions in Issues

### Contact Author

- 📧 **Email**: jerry-shaw@live.com, 27206617@qq.com, 904428723@qq.com
- ⭐ **Star support**: If you like the framework, welcome to give a Star!

### Version Updates

- 🔔 **Follow Releases**: Get latest versions and update announcements

---

**Nervsys** - Process data streams intelligently like the nervous system, building efficient PHP applications.