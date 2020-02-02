# redis
* 简介  
redis缓存类
* 安装/配置  
需要开启php_redis扩展 
* 预定义常量  

* redis -- redis  
    * 构造方法，可传入redis服务器配置
```text
    public function __construct(
            string $host = '127.0.0.1',
            int $port = 6379,
            string $auth = '',
            int $db = 0,
            string $prefix = '',
            int $timeout = 10,
            bool $persist = true,
            string $persist_id = ''
        )
```   
    * redis连接
```text
    public function connect(): \Redis
```   
* 范例  
    Example #1 redis
```php
<?php
use ext\redis;

class test
{  
     public function redis()
     {
         $redis = redis::new()->connect();
         $redis->set('aa','123');
         echo $redis->get('aa');
     }
}
```
* 异常/异常处理  
 