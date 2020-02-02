# http
* 简介  
http请求类
* 安装/配置   
* 预定义常量  
* http -- http类  
    * http::add --添加请求 
```text
    public function add(array $job = []): object
```   
    * http::fetch --请求一条url
```text
    public function fetch(): string
```   
    * http::fetch_all --请求所有url 
```text
    public function fetch_all(): array
```   
* 范例  
    Example #1 http
```php
<?php
$url = 'https://www.baidu.com/';
$res = http::new()->add(['url'=>$url,'data'=>[],'method'=>'get'])->fetch();             //返回请求结果
```
* 异常/异常处理  
 