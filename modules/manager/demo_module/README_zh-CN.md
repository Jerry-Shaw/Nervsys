# 你的模块名称

## 快速开始

1. 将 `module.json` 中的 `your_module_name` 替换为实际模块名称（必须与模块目录名一致）
2. 将模块目录重命名为与 `module.json` 中 `name` 字段相同的名称
3. 将 `go.php` 中的命名空间更新为 `modules\{your_module_name}`
4. 在 `go.php` 中编写业务逻辑，或创建独立的类文件
5. 根据需要向 `module.json` 添加依赖
6. 深入了解请参考 [Nervsys 框架文档]

## 重要命名规则

| 项目                     | 规则                             | 示例                   |
|------------------------|--------------------------------|----------------------|
| 模块目录                   | 必须与 `module.json` 中的 `name` 一致 | `modules/logger/`    |
| `module.json` → `name` | 小写，单词间使用下划线                    | `logger`、`user_auth` |
| `go.php` 中的命名空间        | `modules\{module_name}`        | `modules\logger`     |

## 文件结构

```
{module_name}/
├── go.php          # 入口文件（必需）——暴露方法给 Nervsys 调用
├── module.json     # 模块元数据（必需）
├── README.md       # 本文件
└── app/            # （可选）存放业务逻辑类的目录
    ├── Handler.php
    └── ...
```

## 入口文件（`go.php`）

只有 `go.php` 中的公共方法可以被 Nervsys 调用。推荐将具体业务逻辑拆分到其他类中。

### 基础模板

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;

class go extends Factory
{
    /**
     * 公共方法可直接被 Nervsys 调用
     */
    public function yourMethod(string $param): string
    {
        // 调用你自己的应用逻辑
        return YourAppClass::process($param);
    }
}
```

### 推荐模式：分离应用逻辑

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;
use modules\{your_module_name}\app\YourHandler;

class go extends Factory
{
    public function doSomething(array $data): array
    {
        // 委托给独立处理类
        return YourHandler::new()->handle($data);
    }
}
```

### 自定义应用类示例（`app/YourHandler.php`）

```php
<?php

namespace modules\{your_module_name}\app;

use Nervsys\Core\Factory;

class YourHandler extends Factory
{
    public function handle(array $data): array
    {
        // 在此实现具体业务逻辑
        return $data;
    }
}
```

## 模块元数据（`module.json`）

根据需要编辑以下字段：

- `name`：模块名称（必须与目录名一致）
- `version`：模块版本（推荐语义化版本）
- `description`：模块简要描述
- `author`：模块作者
- `entry`：入口文件名（默认 `go.php`，也可指定其他文件）
- `repo`：本模块的 Git 仓库地址
- `dependencies`：本模块依赖的其他模块
- `requires`：运行时要求（例如 PHP 版本）
- 其他自定义字段：……

### 配置示例

```json
{
  "name": "logger",
  "version": "1.0.0",
  "description": "日志记录工具",
  "author": "Your Name",
  "entry": "go.php",
  "repo": "https://github.com/your/logger",
  "dependencies": {
    "helper": "https://github.com/nervsys/helper.git"
  },
  "requires": {
    "php": ">=8.1"
  }
}
```

## 注意事项

- 模块目录名必须与 `module.json` 中的 `name` 字段严格一致
- 只有 `go.php` 中的公共方法可供框架调用
- 你可以自由组织内部代码结构（例如 `app/`、`lib/`、`config/` 等）
- 本文件为起始模板，请根据实际需求添加文件和逻辑
- 更多详情请参考 Nervsys 框架官方文档

## 许可证

Apache License 2.0