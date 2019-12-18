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
* 范例  
    Example #1 core  
    ```php
    <?php
    ?>
    ```
* 异常/异常处理  
 