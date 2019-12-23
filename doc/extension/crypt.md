# cache
* 简介  
加密类
* 安装/配置  
* 预定义常量  
* crypt -- crypt  
    * crypt::__construct --构造方法 
    ```text
    public function __construct(string $keygen = keygen::class, string $conf_path = ROOT . DIRECTORY_SEPARATOR . 'openssl.cnf')
    ```   
    * crypt::get_key --获取一个秘钥串
    ```text
    public function get_key(): string
    ```   
    * cache::rsa_keys --生成rsa keys
    ```text
    public function rsa_keys(): array
    ```  
    * cache::encrypt --加密
    ```text
    public function encrypt(string $string, string $key): string
    ```
    * cache::decrypt --解密
    ```text
    public function decrypt(string $string, string $key): string
    ```
    * cache::rsa_encrypt --rsa加密
    ```text
    public function rsa_encrypt(string $string, string $key): string
    ```
    * cache::hash_pwd --生成密码
    ```text
    public function hash_pwd(string $string, string $key): string
    ```
    * cache::check_pwd --检查密码
    ```text
    public function check_pwd(string $input, string $key, string $hash): bool
    ```
    * cache::sign --生成签名
    ```text
    public function sign(string $string, string $rsa_key = ''): string
    ```
    * cache::verify --解析签名
    ```text
    public function verify(string $string, string $rsa_key = ''): string
    ```
* 范例  
    Example #1 cache
    ```php
    <?php
      $obj    = crypt::new();
      echo $obj->get_key();                                                           //输出24597eba6e796138984e3a97f0dcc0c2
      echo $obj->encrypt('{"result"=>1}','24597eba6e796138984e3a97f0dcc0c2');         //将json数据加密
      echo $obj->decrypt('wvt3ajxx1Um52RZd1Q', '24597eba6e796138984e3a97f0dcc0c2');   //将加密过的数据解密
      $rsa_keys = $obj->rsa_keys();                                                   //生成包含public和private的数组
      $enc      = $obj->rsa_encrypt('test', $rsa_keys['public']);                     //生成加密过的test
      $dec      = $obj->rsa_decrypt($enc, $rsa_keys['private']);                      //解密之后得到test
      $sign = $obj->sign('123456',$rsa_keys['public']);                               //生成签名
      $verify = $obj->verify($sign,$rsa_keys['private']);                             //解析签名
      $pwd = $obj->hash_pwd('000000','24597eba6e796138984e3a97f0dcc0c2');             //生成密码
      $res = $obj->check_pwd('000000','24597eba6e796138984e3a97f0dcc0c2',$pwd);       //检查密码
    ?>
    ```
* 异常/异常处理  
 