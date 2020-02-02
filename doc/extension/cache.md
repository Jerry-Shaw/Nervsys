# cache
* 简介  
简单的数据缓存（依赖redis）
* 安装/配置  
需求 : redis  
* 预定义常量  
PREFIX(string)--缓存key前缀
* cache -- cache类  
    * cache::__construct --构造方法 
    ```text
    public function __construct(array $conf = [])
    ```   
    * cache::set --添加缓存
    ```text
    public function set(string $key, array $data, int $life = 600): bool
    ```   
    * cache::get --读取缓存
    ```text
    public function get(string $key): array
    ```  
    * cache::del --清除缓存
    ```text
    public function del(string $key): int
    ```
* 范例  
    Example #1 cache
```php
<?php
    $conf = [
        'host'=>'127.0.0.1',
        'auth'=>'',
        'db'=>1
    ];
    $obj = cache::new($conf);
    $obj->set('cache_test', ['cache1' => 1]);   //会存储key：CAS:cache_test,value:{"cache1":1}
    $obj->get('cache_test');                    //会返回数组{"cache1":1}
    $obj->del('cache_test');                    //会清除CAS:cache_test
?>
```
* 异常/异常处理  
 