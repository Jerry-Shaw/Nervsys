# Nervsys - Minimalist PHP Framework

[![release](https://img.shields.io/badge/release-8.2.8-blue?style=flat-square)](https://github.com/Jerry-Shaw/Nervsys/releases)
[![php](https://img.shields.io/badge/php-8.1+-brightgreen?style=flat-square)](https://www.php.net/)
[![license](https://img.shields.io/badge/license-Apache%202.0-blue?style=flat-square)](LICENSE.md)

### README: [English](README.md) | [简体中文](README_zh-CN.md)

## Overview

**Nervsys** (derived from "Nerve System") is a minimalist, high-performance PHP framework designed for modern web applications and API development. Inspired by the neural system, the framework aims to process data streams flexibly like nerve cells, helping developers build intelligent application systems based on pure data calls.

## 🚀 Core Features

- **Lightweight Design**: Core components are streamlined without redundant dependencies
- **Intelligent Routing**: Automatic parameter mapping reduces repetitive code
- **Dual Mode Support**: Supports both Web (CGI) and Command Line (CLI) modes
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
   ├── api/           # API interface classes
   │   └── User.php   # Example API
   ├── app/           # Application classes
   │   ├── Service/   # Business services
   │   └── Middleware/# Middleware
   ├── www/           # Web entry directory
   │   └── index.php  # Main entry file
   └── logs/          # Log directory (auto-created)
   ```

3. **Configure entry file** (`www/index.php`)

   ```php
   <?php
   require __DIR__ . '/../Nervsys/NS.php';
   
   $ns = new Nervsys\NS();
   
   // Optional configuration (set as needed)
   $ns->setApiDir('api')                    // API directory
      ->setDebugMode(true)                  // Debug mode (turn off in production)
      ->setContentType('application/json')  // Response type
      ->assignHook([\app\middleware\Auth::new(), 'check'], '/api/')
      ->addAutoloadPath(__DIR__ . '/../vendor');
   
   $ns->go();
   ```

---

## 📖 Usage Guide

### Create Your First API

1. **Define API class** (`api/User.php`)

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
                   'message' => 'Login successful',
                   'token' => md5($username . time())
               ];
           }
           
           return ['success' => false, 'message' => 'Invalid username or password'];
       }
       
       public function getProfile(int $userId, string $fields = 'basic'): array
       {
           $userData = [
               'id' => $userId,
               'name' => 'John Doe',
               'email' => 'john@example.com',
               'age' => 28
           ];
           
           return ['success' => true, 'data' => $userData];
       }
   }
   ```

2. **Call the API**

   **Web method:**
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

   **CLI method:**
   ```bash
   # Parameter call
   php index.php -c"user/login" -d'username=admin&password=123456'
   
   # JSON data
   php index.php -c"user/login" -d'{"username":"admin","password":"123456"}'
   
   # Specify output format
   php index.php -c"user/getProfile" -d'userId=123' -r"json"
   ```

---

## 🎯 Quick Start Guide

Nervsys adopts the **"Convention Over Configuration"** design philosophy, allowing you to skip complex configuration steps and start developing efficiently with intelligent default settings.

### 📌 Core Principles

1. **Centralized Configuration**  
   All framework configuration functions are integrated into `System Trait`. You don't need to search for configuration options in different files - one-stop configuration management.

2. **Simplified Initialization**  
   In the entry file, after creating an instance with `new NS()`, simply chain-call the configuration methods you need to quickly complete framework initialization.

3. **Learn as Needed, Progress Gradually**
  - **Beginner**: Focus on writing your business APIs without worrying about underlying implementations
  - **Intermediate user**: Learn about specific modules like routing and middleware when needed
  - **Advanced developer**: Explore advanced features like concurrent processing and Socket communication as required

### 🛠️ Extension Support

- **Built-in extension classes**: Common functional enhancement classes are pre-built in the `Ext` directory, ready to use out of the box
- **Extension documentation**: Detailed extension class usage manuals are being actively developed - stay tuned!

---

💡 **Development Philosophy**: Get it running first, then optimize and adjust as needed. Nervsys is designed to help you start quickly and smoothly transition to advanced features.

---

## 🏗️ Core Components

### System Configuration (System Trait)
**Function**: Provides a complete framework configuration interface containing all runtime configuration options. Serves as the core control center of the framework.

**Main Configuration Methods**:

**Path & Directory Configuration**:
- `setRootPath($path)` - Set application root directory
- `setApiDir($dir_name)` - Set API directory
- `addAutoloadPath($path, $prepend = false)` - Add autoload path

**Runtime Environment Configuration**:
- `setTimezone($timezone)` - Set timezone
- `setDebugMode($debug_mode)` - Set debug mode
- `setLocale($locale)` - Set locale/language

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
$ns = new Nervsys\NS('/var/www/myapp', 'api/v2');
$ns->setRootPath('/var/www/app')
    ->setApiDir('api/v1')
    ->setDebugMode($_ENV['APP_DEBUG'] ?? false)
    ->addCorsRule('https://example.com', 'Authorization,Content-Type')
    ->assignHook([$auth, 'checkToken'], '/api/', '/api/auth/login')
    ->addAutoloadPath(__DIR__ . '/vendor')
    ->go();
```

### Application Environment (App Class)
**Function**: Manages application runtime environment and configuration information, providing a global access point.

**Main Methods**:
- `setRoot($root_path)` - Set root directory
- `setApiDir($api_dir)` - Set API directory
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
echo "Client IP: " . $app->client_ip;
echo "Running mode: " . ($app->is_cli ? 'CLI' : 'Web');
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

// Add custom error handlers
$error->addErrorHandler([$monitor, 'trackError']);
$error->addErrorHandler([$notifier, 'sendAlert']);

// Record custom log
$logContent = $error->formatLog('warning', 'API call frequency too high', [
    'user_id' => 123,
    'endpoint' => '/api/user/profile',
    'count' => 100
]);
$error->saveLog($app, 'security.log', $logContent);
```

### Hook System (Hook Class)
**Function**: Provides flexible middleware and pre-processing mechanisms, achieving separation of cross-cutting concerns.

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

// Register authentication hook
$hook->assign([$auth, 'checkToken'], '/api/', '/api/auth/login');

// Register logging hook
$hook->assign([$logger, 'accessLog'], '/');

// Execute hook
if ($hook->run('/api/user/getInfo')) {
    // Hook check passed, continue processing
}
```

### Object Factory (Factory Class)
**Function**: Provides intelligent object creation and dependency injection, simplifying object lifecycle management.

**Main Methods**:
- `new()` - Create current class instance
- `getObj($class_name, $class_args = [], $user_passed = false)` - Get object instance
- `buildArgs($param_reflects, $data_package)` - Build parameter array

**Features**:
- Automatic dependency injection - Automatically resolves and injects constructor dependencies
- Object reuse (Singleton pattern) - Intelligently manages object instances for performance
- Automatic parameter mapping - Automatically matches input data based on parameter names
- Type-safe conversion - Automatically performs type conversion and validation

**Example**:
```php
class UserService extends \Nervsys\Core\Factory {
    public function __construct(\Nervsys\Ext\libMySQL $db, int $user_id) {
        // $db instance automatically injected
    }
}

// $db instance automatically injected, and $user_id = 1 passed to constructor
$service = UserService::new(['user_id' => 1]);
```

### Routing System (Router Class)
**Function**: Handles request routing parsing, supporting both Web and command line dual modes with highly flexible routing mechanisms.

**Main Methods**:
- `parseCgi($c)` - Parse Web request routes
- `parseCli($c)` - Parse command line request routes
- `addCgiRouter($router)` - Add Web route processor
- `addExePathMapping($exe_name, $exe_path)` - Add executable file mapping

**Routing Features**:
- Routing stack mechanism (priority processing) - Supports multi-level route processors
- Custom route processors - Complete control over routing logic
- Executable file mapping - Convenient integration with external tools
- Path normalization - Unified path format processing

**Example**:
```php
$router = Router::new();

// Custom route processor
$router->addCgiRouter(function($path) {
    if (str_starts_with($path, 'v2/')) {
        return ['Api\\V2\\' . str_replace('/', '\\', substr($path, 3)), 'handle'];
    }
    return [];
});

// Add CLI command mapping
$router->addExePathMapping('python', '/usr/bin/python3');

// Parse routes
$webRoute = $router->parseCgi('user/profile/getInfo'); // ['api\\user\\profile', 'getInfo']
$cliRoute = $router->parseCli('python script.py');     // ['python', '/usr/bin/python3']
```

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

// Configure data reading
$ioData->readHeaderKeys('Authorization', 'X-API-Version');
$ioData->readCookieKeys('session_token');

// Read Web request
$ioData->readCgi();

// Get processed data
$inputData = $ioData->getInputData(true, true);

// Set output
$ioData->src_output = ['success' => true, 'data' => $result];
$ioData->setContentType('application/json');

// Output response
$ioData->output();
```

### Security Protection (Security Class)
**Function**: Provides comprehensive security protection features to ensure application data security.

**Main Methods**:
- `getApiResource($class_name, $method_name, $class_args = [], $filter = null)` - Validate API resources
- `antiXss($data)` - XSS attack protection

**Security Features**:
- API resource security validation - Ensures only legitimate APIs can be called
- Automatic XSS filtering - Intelligent protection against cross-site scripting attacks
- Framework core class protection - Prevents malicious calls to framework core components

**Example**:
```php
$security = Security::new();

// Configure XSS filter skip keys
$security->addXssSkipKeys('html_content', 'raw_data', 'code_block');

// Execute XSS protection
$safeData = $security->antiXss($_POST);
```

### Reflection Management (Reflect Class)
**Function**: Provides efficient reflection information cache management, significantly improving reflection operation performance.

**Main Methods**:
- `getClass($class_name)` - Get class reflection information
- `getMethod($class_name, $method_name)` - Get method reflection information
- `getMethods($class_name, $filter = null)` - Get all class methods

**Performance Optimization**:
- Intelligent caching of reflection objects - Avoids repeated reflection operation overhead
- Reduced duplicate reflection operations - Improves overall framework performance
- Batch retrieval support - Retrieves multiple reflection information at once

**Example**:
```php
// Get class method information
$methods = Reflect::getMethods('App\Controller\UserController', \ReflectionMethod::IS_PUBLIC);

// Get parameter information
$method = Reflect::getMethod('UserService', 'createUser');
$params = $method->getParameters();

foreach ($params as $param) {
    $info = Reflect::getParameterInfo($param);
    // $info contains name, type, default value, etc.
}
```

### Cross-Origin Processing (CORS Class)
**Function**: Handles cross-origin requests and security configuration, simplifying CORS setup.

**Main Methods**:
- `addRule($allowed_origin, $allowed_headers = '', $exposed_headers = '')` - Add cross-origin rules
- `checkPermission($is_https)` - Check and process cross-origin requests

**Supported Features**:
- Multiple domain configuration - Supports access from multiple origins
- Custom request headers - Flexible control over request header permissions
- Preflight request handling - Automatically processes OPTIONS preflight requests
- Security validation - Ensures cross-origin request safety

**Example**:
```php
$cors = CORS::new();
$cors->addRule('https://example.com', 'Authorization,Content-Type,X-API-Key')
    ->addRule('http://localhost:3000', 'Content-Type')
    ->addRule('*'); // Allow all origins (use with caution)

// Call before request processing
$cors->checkPermission($app->is_https);
```

### Method Caller (Caller Class)
**Function**: Safely executes API methods and external programs, providing unified calling interface.

**Main Methods**:
- `runApiFn($cmd, $api_args, $anti_xss)` - Execute API method calls
- `runProgram($cmd_pair, $cmd_argv = [], $cwd_path = '', $realtime_debug = false)` - Execute external programs

**Security Features**:
- Automatic XSS protection - Built-in security protection mechanism
- API resource validation - Ensures call legitimacy
- Exception-safe handling - Graceful handling of call exceptions

**Example**:
```php
$caller = Caller::new();

// Execute API method
$result = $caller->runApiFn(
    ['App\Controller\UserController', 'getProfile'],
    ['userId' => 123, 'fields' => 'all'],
    true // Enable XSS protection
);

// Execute system command
$output = $caller->runProgram(
    ['ls', '/usr/bin/ls'],
    ['-la', '/var/log'],
    '/tmp',
    false
);
```

### Performance Analysis (Profiler Class)
**Function**: Code performance monitoring and analysis, helping identify performance bottlenecks.

**Main Methods**:
- `setThresholds($memory_bytes, $time_milliseconds)` - Set performance thresholds
- `start($profile_name, $analyze_cli = false)` - Start performance analysis
- `end($profile_name, $force_save = false, $with_input_data = false, $log_file_name = 'profiler')` - End performance analysis

**Monitoring Metrics**:
- Execution time - Millisecond-precision execution time monitoring
- Memory usage - Real-time memory usage tracking
- Call counts - Statistics on code block call frequency
- Threshold warnings - Automatic detection of performance bottlenecks

**Example**:
```php
$profiler = Profiler::new();

// Set thresholds: 1MB memory, 100ms time
$profiler->setThresholds(1024 * 1024, 100);

// Monitor database query
$profiler->start('database_query');
$result = $db->query('SELECT * FROM users WHERE active = 1');
$profiler->end('database_query');

// Detailed logs are automatically recorded if thresholds are exceeded
// Log content: time, memory, parameters, etc., for performance tuning
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

// Get system information
$ipv4Addresses = $osMgr->getIPv4();
$hardwareHash = $osMgr->getHwHash();
$phpPath = $osMgr->getPhpPath();

// Build background command
$command = $osMgr->inBackground(true)->buildCmd('php worker.php');

// Execute system command
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
- Process pool load balancing - Intelligent task distribution to idle processes
- Task queue management - Ordered processing of large task volumes
- Inter-process communication - Supports data exchange between processes
- Error recovery mechanism - Automatic handling of process exceptions

**Example**:
```php
$procMgr = ProcMgr::new();

// Start 4 worker processes
$procMgr->command(['php', 'worker.php'])
    ->runMP(4, 1000);

// Submit batch tasks
for ($i = 0; $i < 100; $i++) {
    $procMgr->putJob(
        json_encode(['task_id' => $i, 'data' => 'task_' . $i]),
        function($output) use ($i) {
            echo "Task {$i} completed: {$output}\n";
        }
    );
}

// Wait for all tasks to complete
$procMgr->awaitJobs();
```

### Fiber Manager (FiberMgr Class)
**Function**: PHP fiber (coroutine) management, implementing lightweight concurrent processing.

**Main Methods**:
- `await($callable, $arguments = [])` - Create and start fibers
- `async($callable, $arguments = [], $callback = null)` - Add asynchronous tasks
- `commit()` - Submit and execute all asynchronous tasks

**Fiber Features**:
- Lightweight concurrency - Extremely low memory overhead
- Cooperative scheduling - Fibers voluntarily yield execution
- Low memory overhead - Much lighter than threads and processes
- Single-threaded concurrency - Achieves concurrency effects within a single thread

**Example**:
```php
$fiberMgr = FiberMgr::new();

// Add asynchronous task
$fiberMgr->async(
    function($userId) {
        Fiber::suspend(); // Suspend fiber
        return fetchUserData($userId);
    },
    ['userId' => 123],
    function($result) {
        echo "User data: " . json_encode($result) . "\n";
    }
);

// Submit and execute all tasks
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
- TCP - Reliable byte stream transmission
- UDP - Connectionless datagram transmission
- WebSocket - Full-duplex communication protocol
- SSL/TLS - Secure encrypted transmission

**Example**:
```php
$socketMgr = SocketMgr::new();

// Configure WebSocket server
$socketMgr->setDebugMode(true)
    ->onConnect(function($socketId) {
        echo "Client {$socketId} connected\n";
    })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        echo "Message received: {$message}\n";
        // Process message and reply
        $socketMgr->sendMessage($socketId, "Received: {$message}");
    });

// Start WebSocket server
$socketMgr->listenTo('tcp://0.0.0.0:8080', true);
```

---

## 🔧 Advanced Features

### Process Management (ProcMgr Class)
Multi-process task processing, suitable for batch data processing and high-concurrency scenarios.

```php
$procMgr = ProcMgr::new()
    ->command(['php', 'worker.php'])
    ->runMP(4); // Start 4 worker processes

// Submit tasks
for ($i = 0; $i < 100; $i++) {
    $procMgr->putJob(
        json_encode(['task_id' => $i]),
        function($output) use ($i) {
            echo "Task {$i} completed: {$output}\n";
        }
    );
}

$procMgr->awaitJobs();
```

### WebSocket Communication (SocketMgr Class)
Real-time communication support, easily building chat applications, real-time notifications, etc.

```php
$socketMgr = SocketMgr::new()
    ->setDebugMode(true)
    ->onConnect(function($socketId) {
        echo "Client {$socketId} connected\n";
    })
    ->onMessage(function($socketId, $message) use ($socketMgr) {
        $socketMgr->sendMessage($socketId, "Received: {$message}");
    })
    ->listenTo('tcp://0.0.0.0:8080', true);
```

### Performance Analysis (Profiler Class)
Code performance monitoring to help optimize application performance.

```php
$profiler = Profiler::new();
$profiler->setThresholds(1024 * 1024, 100); // 1MB memory, 100ms time

$profiler->start('database_query');
$result = $db->query('SELECT * FROM users');
$profiler->end('database_query'); // Auto-log when thresholds exceeded
```

### Middleware System
Implement flexible middleware mechanism through hook system, handling cross-cutting concerns like authentication, logging, etc.

```php
// Create authentication middleware
class AuthMiddleware
{
    public function checkToken($token): bool
    {
        return $this->validateToken($token);
    }
}

// Register middleware
$ns->assignHook([new AuthMiddleware(), 'checkToken'], '/api/', '/api/auth/login');
```

### Custom Routing
Extend default routing mechanism to implement advanced routing features like RESTful routing.

```php
// Custom route processor
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

### Real-time Communication Application
Build complete real-time chat server, showcasing SocketMgr's powerful capabilities.

```php
// Real-time chat server
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

## 📁 Project Structure Recommendation

```
project/
├── api/                   # API interface layer
│   ├── v1/               # Version 1
│   └── v2/               # Version 2
├── app/                  # Application layer
│   ├── Service/          # Business services
│   ├── Model/           # Data models
│   └── Middleware/      # Middleware
├── config/              # Configuration files
├── logs/                # Logs
├── public/              # Web entry
│   └── index.php
└── vendor/              # Dependencies
```

### Environment Configuration Management
Manage different environment configurations through environment variables, achieving configuration-code separation.

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

// Entry file configuration
$env = $_SERVER['APP_ENV'] ?? 'development';
$config = require __DIR__ . '/../config/environment.php';

$ns = new Nervsys\NS();
$ns->setDebugMode($config[$env]['debug']);
// ... other configurations
```

### Error Monitoring and Logging
Implement custom error handlers for more friendly error information and comprehensive logging.

```php
// Custom error handler
class CustomErrorHandler
{
    public function handle(App $app, IOData $ioData, Throwable $e, bool $report): void
    {
        // Record detailed error information
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
        
        // Return generic error in production
        if (!$app->debug_mode) {
            $ioData->src_msg = [
                'code' => 500,
                'message' => 'System busy, please try again later',
                'request_id' => uniqid()
            ];
        }
    }
}

// Register error handler
$error->addErrorHandler([new CustomErrorHandler(), 'handle']);
```

---

## ❓ Frequently Asked Questions

### Q: How to upgrade the framework?
**A:** Simply replace the framework files. Nervsys uses stateless design, making upgrades simple and safe. We recommend backing up your project code before upgrading, just in case.

### Q: Does it support PHP 8.2/8.3/8.4/8.5?
**A:** Fully supports all versions from PHP 8.1+, including the latest PHP 8.5. The framework continuously follows PHP's latest features.

### Q: How to handle database operations?
**A:** The framework includes two database processing solutions:
- **General solution**: `Ext\libPDO` - Supports MySQL, MSSQL, PostgreSQL, Oracle and more, providing standard PDO interface
- **MySQL-specific**: `Ext\libMySQL` - Provides more convenient MySQL operation interface with encapsulated common operations

### Q: How to integrate third-party libraries (like Composer packages)?
**A:** Use the `addAutoloadPath()` method to add Composer's vendor directory:
```php
$ns->addAutoloadPath(__DIR__ . '/../vendor');
```

### Q: How to configure for production environment?
**A:**
1. **Debug mode**: Make sure to turn off debug mode (`setDebugMode(false)`)
2. **Error handling**: Configure appropriate error handlers to avoid sensitive information leaks
3. **File permissions**: Set correct file permissions (logs directory needs write access)
4. **Server configuration**: Deploy like any regular PHP project, supports common servers like Nginx/Apache
5. **Security recommendations**: Enable HTTPS, configure appropriate CORS rules

### Q: What about framework performance?
**A:** Nervsys is designed for high performance:
- **Minimalist core**: Core files only hundreds of KB, reducing unnecessary overhead
- **Fast startup**: Optimized initialization process, rapid response
- **Memory-friendly**: Streamlined code structure and efficient memory management
- **High concurrency support**: Built-in multi-process and fiber support, suitable for high-concurrency scenarios

### Q: How should beginners get started?
**A:** We recommend following this learning path:
1. **Step 1 (5 minutes)**: Configure entry file with `new NS()` and basic settings
2. **Step 2 (15 minutes)**: Write your first API class and test it
3. **Step 3 (as needed)**: Add routing, middleware and other features based on project requirements
4. **Step 4 (advanced)**: Consult corresponding module documentation when advanced features are needed

**Key advice**: No need to deeply understand all modules from the start. Nervsys is designed to help you start quickly and gradually master more features through actual development.

---

## 🤝 Contribution Guidelines

We welcome and appreciate all forms of contributions! Whether reporting bugs, suggesting improvements, or submitting code, all help make Nervsys better.

### Contribution Process
1. **Fork repository**: Click the Fork button in the top-right of GitHub
2. **Create branch**: `git checkout -b feature/your-feature-name`
3. **Commit changes**: `git commit -m 'Add some amazing feature'`
4. **Push branch**: `git push origin feature/your-feature-name`
5. **Create PR**: Create a Pull Request on GitHub describing your changes

### Development Standards
- Follow PSR coding standards
- Add appropriate comments and documentation
- Ensure code compatibility with PHP 8.1+
- Provide usage examples for functional changes

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
- 📚 **Documentation**: This README is the main documentation containing complete framework usage guide
- 🐛 **Issue reporting**: [GitHub Issues](https://github.com/Jerry-Shaw/Nervsys/issues)
- 💬 **Technical discussion**: Welcome to start technical discussions in Issues

### Contact Author
- 📧 **Email**: jerry-shaw@live.com
- ⭐ **Star support**: If you like the framework, welcome to give a Star to support project development!

### Version Updates
- 🔔 **Follow Releases**: Get latest versions and update announcements
- 📝 **Changelog**: Important changes are detailed in Releases

---

**Nervsys** - Process data streams intelligently like the nervous system, building efficient PHP applications.