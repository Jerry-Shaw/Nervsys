# queue
* 简介  
队列执行类(依赖redis)
* 安装/配置  
需要开启php_redis扩展 
* 预定义常量  

* queue -- queue  
    * 构造方法，传入redis对象
```text
    public function __construct(\Redis $redis)
```   
    * 设置以name区分队列实例
```text
    public function set_name(string $name): object
```   
    * 添加队列任务，其中type可设置为实时任务，延时任务，唯一防并发任务
```text
    public function add(string $cmd, array $data = [], string $group = 'main', string $type = self::TYPE_REALTIME, int $time = 0): int
```   
    * 杀掉进程
```text
   public function kill(string $proc_hash = ''): int
```   
    * 查看队列执行日志（成功/失败）
```text
     public function show_logs(string $type = 'success', int $start = 0, int $end = -1): array
```   
    * 查看某一组队列的任务数量
```text
     public function show_length(string $queue_key): int
```   
    * 查看待执行的队列组
```text
     public function show_queue(): array
```   
    * 查看进程
```text
    public function show_process(): array
```  
    * 队列执行方法
```text
    public function go(int $max_fork = 10, int $max_exec = 1000): void
```   
* 范例  
    Example #1 queue
```php
<?php
use ext\queue;

class test
{  
    public function queue()
    {
         $redis = redis::new()->connect();
         $queue = queue::new($redis);
         $queue->set_name('app4')->add('test3-redis',['a'=>3],'test6');
         //查看失败队列
         $fail_list = $queue->set_name('app4')->show_logs('failed', 0, 20);
         //查看任务数
         $que_len = $queue->set_name('app4')->show_length('Q:app4:jobs:test6');
         //查看待执行的队列组
         $que_list = $queue->set_name('app4')->show_queue();
         //查看进程
         $process_list = $queue->set_name('app4')->show_process();
    }
}
```
* 异常/异常处理  
 