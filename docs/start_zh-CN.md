# 入门

## 安装

### 要求

- PHP >= 7.4.0

### 创建项目

首先我们先预先设置几个目录。

- NervSys代码存放的目录是 `/data/code/ns`.
- 开发项目存储的目录是 `/data/code/hello`.

首先我们先拉取最新代码到NervSys项目到 `/data/code/ns`.

```bash
git clone https://github.com/Jerry-Shaw/NervSys.git /data/code/ns
```

接下来就是创建业务环境了，我们接下来的操作都是在 `/data/code/hello`项目下进行操作了。

首先我们执行以下命令创建项目目录：

```bash
mkdir -p /data/code/hello/public/
```

接下来我们创建访问入口文件：

```bash
cat <<EOF >  /data/code/hello/public/index.php
<?php
require '/data/code/ns/NS.php';

//可选，如果需要的话，请参阅"Ext/libCoreApi.php"
\Ext\libCoreApi::new()
    //打开核心调试模式 (错误信息会随着结果显示出来)
    ->setCoreDebug(true)
    //打开全局跨域许可 (默认请求头)
    ->addCorsRecord('*')
    //设置输出格式为"application/json; charset=utf-8"
    ->setContentType('application/json');

NS::new();
EOF
```

### 运行项目

在 `/data/code/hello/public/` 目录执行以下命令：

```bash
php -S localhost:8000 
```

会看到响应值：`[]`。这样就表示我们的项目已经搭建成功了。

## 创建第一个接口

首先我们创建一个 class 在 `api` 目录。 文件名为 `user.php`。

```php
<?php

namespace api;

class user
{

    public function login(): array
    {
        return [
            "a" => 1
        ];
    }
}
```

然后访问 `http://localhost/?c=user/login`。

得到响应值：`{"a":1}`.

## CLI 访问

首先我们创建一个 class 在 `app` 目录。 文件名为 `command.php`。

```php
<?php

namespace app;

class command
{

    public function demo(): array
    {
        return "hello";
    }
}
```

最后执行以下命令获取结果值：

```bash
$ php public/index.php -r /app/command/demo
success
```