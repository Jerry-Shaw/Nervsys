# conf
* 简介  
从ini配置文件获取数据
* 安装/配置   
* 预定义常量  
* conf -- conf类
    * conf::load --导入设置文件
    ```text
    public static function load(string $file_path, string $file_name): array
    ```   
    * conf::set --设置配置
    ```text
    public static function set(string $section, array $values): void
    ```
    * conf::get --读取配置
    ```text
    public static function get(string $section): array
    ```  
* 范例  
    Example #1 conf  
    prod.ini
    ```text
    [redis]
    host = "127.0.0.1"
    auth =
    db = 0
    ```
    ```php
    <?php
      conf::load('conf','prod');      //导入conf目录下的prod.ini文件
      conf::set('redis',['db'=>1]);   //设置配置中的db为0
      conf::get('redis');             //获取到数据为数组{"host":"127.0.0.1","auth":"","db":1}
    ?>
    ```
* 异常/异常处理  
 