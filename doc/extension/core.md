# core
* 简介  
从ini配置文件获取数据
* 安装/配置   
* 预定义常量  
* core -- core类
    * core::stop --程序停止
    ```text
    public static function stop(): void
    ```   
    * core::get_ip --获得请求方的ip地址
    ```text
    public static function get_ip(): string
    ```    
    * core::get_uuid --获得UUID
    ```text
    public static function get_uuid(string $string = ''): string
    ```   
    * core::get_log_path --系统日志存储理解
    ```text
    public static function get_log_path(): string
    ```   
    * core::get_php_path --php可执行文件路径
    ```text
    public static function get_php_path(): string
    ```   
    * core::get_cmd_list --获得cmd列表
    ```text
    public static function get_cmd_list(): array
    ```   
    * core::register_router_function --注册自定义路由
    ```text
    public static function register_router_function(array $router): void
    ```   
    * core::set_output_handler --自定义输出模块
    ```text
    public static function set_output_handler(array $handler): void
    ```   
    * core::is_CLI --是否cli执行
    ```text
    public static function is_CLI(): bool
    ```   
    * core::is_TLS --是否TLS执行
    ```text
    public static function is_TLS(): bool
    ```   
    * core::add_error --添加自定义错误信息
    ```text
    public static function add_error(string $key, $error): void
    ```   
    * core::set_error --设置自定义错误信息
    ```text
    public static function set_error(array $error): void
    ```   
    * core::add_data --添加全局参数
    ```text
    public static function add_data(string $key, $value): void
    ```   
    * core::get_data --获取全局参数
    ```text
    public static function get_data(string $key = '')
    ```   
* 范例  
    Example #1 core  
    ```php
    <?php
      use ext\core;echo 1;core::stop();echo 2;     //仅输出1
      echo core::get_ip();            //输出127.0.0.1（cli则是Local CLI）
      echo core::get_uuid('a');       //输出0cc175b9-c0f1-b6a8-31c3-99e269772661
      echo core::get_log_path();      //输出C:\NervSys\logs
      echo core::get_php_path();      //输出C:\PHP\php.exe
      echo core::is_CLI();            //输出true/false
      echo core::is_TLS();            //输出true/false
      core::add_error('error','文件未找到');          //添加错误
      core::set_error(['error'=>'文件未找到']);       //设置错误
      core::add_data('param', '参数1');     //添加全局变量
      core::get_data('param');              //获取全局变量
    ?>
    ```
    Example #2 core::register_router_function注册路由  
    router--路由解析类
    ```text
    <?php
    namespace lib;
    
    use core\lib\stc\factory;
    use core\lib\std\pool;
    
    class router
    {
       public $get    = ['a' => 'app\test1-a'];
       public $post   = ['a' => 'app\test1-b'];
       public $patch  = ['a' => 'app\test1-c'];
       public $delete = ['a' => 'app\test1-d'];
     
       public function parse(string $cmd)
       {
          if (!isset($_SERVER['REQUEST_METHOD'])){
              return [];
          }
          $method = strtolower($_SERVER['REQUEST_METHOD']);
          if (isset($this->$method[$cmd])) {
              factory::build(pool::class)->cmd = $this->$method[$cmd];
          }
          return [];
       }
    }
    ?>
    ```
    api.php--入口文件注册路由
    ```php
    <?php
    core::register_router_function([new router(), 'parse']);
    ?>
    ```
    Example #3 core::set_output_handler重定义输出  
    ```
    api.php--入口文件重定义输出
    ```php
    <?php
    \ext\core::set_output_handler([new output(), 'handle']);
    ?>
    ```
* 异常/异常处理  
 