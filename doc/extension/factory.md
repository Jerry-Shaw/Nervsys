# factory
* 简介  
工厂类
* 安装/配置   
* 预定义常量  
* factory -- factory类  
    * factory::create --创建类 
```text
    public static function create(array $arguments = []): object
```   
    * factory::new --实例化类
```text
    public static function new(): object
```   
    * factory::as --别名
```text
    public function as(string $alias): object
```  
    * factory::use --使用某个类
```text
    public static function use(string $alias): object
```
* 范例  
    Example #1 factory  
    test类
```php
<?php
class test extends factory
{
    public $a;

    public function __construct(int $param)
    {
        $this->a = $param;
    }

    public function fun_a()
    {
        return $this->a;
    }
}
```
    工厂使用
```php
<?php
$a = test::create(['param' => 1])->as('a');
$b = test::new(2)->as('b');
$c = test::new(3)->as('c');
$d = test::use('b');
echo $a->fun_a();     //返回1
echo $b->fun_a();     //返回2
echo $c->fun_a();     //返回3
echo $d->fun_a();     //返回2
```
* 异常/异常处理  
 