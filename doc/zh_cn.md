# Ns 实践操作文档


> 项目目录功能介绍：
    
    1、项目采用单入口形式 根目录 api.php
    2、应用业务代码目录请自建，本项目只存在内核部分
    3、core 目录属于项目内核部分，有兴趣的可以研究内核
    4、core 下的system.ini 项目基础设置
    5、ext 应用扩展功能，可自行添加或者修改
    6、logs 为日志
    

> 项目基础设置core/system.ini
    
    1、 Log 日志及错误设置，线上请关闭调试模式
    2、CORS 设置跨域：
        1、单个设置 ：http://your.domain.com = X-Requested-With, Content-Type, Content-Length
        2、设置所有 ：* = X-Requested-With, Content-Type, Content-Length
        
    3、LOAD 加载内容，相当于项目初始前置
    4、PATH 路径目录
    5  可自行添加设置，如：
        [DOMAIN]
        domain = http://www.xxxx.com
        
        相应的应在core/pool/setting 添加一个属性
        
        protected static $domain = [];
        
        应用业务代码 使用    self::$domain['domain'] 获取设定的参数
        

> 建立应用目录，创建一个文件
    
    1、在根目录出建立一个文件夹（自定义名）如 gerden
    2、建立文件，如:test.php
    3、设置及继承内核：
        
        <?php
        namespace gerden;
        
        use core\handler\factory;
 
   
        class test extends factory
        {
            /**
             * 对外开放的接口
             * @var array
             */
            public static $tz = [
                'pushImage'=>[
                    'param' => 'token,nonce,timestamp,signature',// 该函数对外接收参数
                    'pre'=>'xxx/base-token' // 前置函数，运行当个函数之前运行
                    'post'=>'xxx/base-xxxx' //后置函数，运行当个函数之后运行
                ]
        
            ];
            
            public function pushImage()
            {
                
            }
        }
        
    4、访问pushImage 方法
        
        https://www.xxx.com/api.php?c=gerden/test-pushImage
        
    5、参数传递可接受key=>value 与 json流
        
> 异常抛出

        public function gettest()
        {
            if(parent::$error){ // 上层扑抓系统级错误
                return parent::$error;
            }
    
            $res = [
                'code'=>200,
                'msg'=>'成功',
            ];
            try{
                $token = parent::$data['token']; // 利用属性获取接收参数
                if(!$token){
                    mine::use()->abortMy(404,'未存在'); //设置错误并中断
                }
               
    
            }catch (\Exception $e){
                $res['code'] = $e->getCode();// 扑住业务级错误
                $res['msg'] = $e->getMessage();
            }
    
            return $res;
        }
        
        


> 数据连接及使用
    
    1、 可在ext/pdo.php 中设置相关的数据库信息
    2、 通过pdo_mysql::use()调用数据库操作
    
        1、查询
             $data = pdo_mysql::use()->select('test')
                        ->where(['id',1])
                        ->order(['id'=>'desc'])
                        ->field('id,name')
                        ->limit(1,10)
                        ->fetch();
         
        2、创建
            $pdo_mysql = new pdo_mysql();
            $pdo_mysql->insert('test')
                ->value([
                    'name'=> '测试',
                ])->execute();
            $id = $pdo_mysql->last_insert(); //获取创建id
            
            
        3、修改
            pdo_mysql::use()->update('test')
                ->where(['id',1])
                ->value([
                    'name'=>'测试',
                ])->execute();
                
        4、自增或减
           pdo_mysql::use()->update('test')
                    ->where(['id',1])
                    ->incr(['money'=>100)])
                    ->execute();
                    
            pdo_mysql::use()->update('test')
                   ->where(['id',1])
                   ->incr(['money'=>-100)])
                   ->execute();
                   

> 文件或上传
    
      /**
         * 上传图片
         * @return array
         * @throws \Exception
         */
        public function pushImage()
        {
            if(parent::$error){
                return parent::$error;
            }
            $res = [
                'code'=>200,
                'msg'=>'成功',
            ];
            try{
                if(strstr(parent::$data['image_push'],'base64,')){
    
                    $file = upload::use()->recv('image_push')->save();  //请注意。此处的image_push必须是接收参数的key，上传的文件会在根目录生成一个uploads 文件夹，请注意服务器相应权限
    
                    if($file['err'] != 0){
                        mine::use()->abortMy(40031,mine::use()->msgClient(40031));
                    }
    
                    $res['data'] = $file['url'];
                }
            }catch (\Exception $e){
                $res['code'] = $e->getCode();
                $res['msg'] = $e->getMessage();
            }
    
            return $res;
        }
        
> redis 使用

    1、设置 ：
        redis_cache::use()->set('123456',[1，2],60); //存储类型必须数组
        
    2、 获取
        redis_cache::use()->get('123456');
        
    3、删除
         redis_cache::use()->del('123456');
         
         
### 注：更多扩展请自行探索  文中 mine::use() 为本人自建扩展

    <?php
    namespace ext;
    use core\handler\factory;
    
    class mine extends factory
    {
     
    
        /**
         * 错误码
         * @param $code
         * @param $msg
         * @throws \Exception
         */
        public function abortMy($code,$msg)
        {
            throw new \Exception($msg,$code);
        }
   
    }