# upload
* 简介  
文件上传（支持file/base64）
* 安装/配置  
需要开启php_fileinfo扩展 
* 预定义常量  

* upload -- upload类  
    * 获取文件，name是文件域名称，as可以设置存储名称，默认是系统生成的唯一名称 
    ```text
    public function fetch(string $name, string $as = ''): object
    ```   
    * 保存文件，to可以设置文件存储路径
    ```text
    public function save(string $to = 'upload'): array
    ```   
* 范例  
    Example #1 upload
    ```php
    <?php
      use ext\upload;
      
      class test
      {  
           public function upload()
           {
               return upload::new()->fetch('photo')->save();
           }
      }
    ?>
    ```
* 异常/异常处理  
 