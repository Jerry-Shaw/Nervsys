# Nervsys Module Manager

模块管理器是 Nervsys 框架的官方模块管理工具，用于从 Git 仓库（GitHub / Gitee / GitLab 等）下载、安装和管理模块。

## 功能特性

- 从 Git 仓库克隆模块到设定的 `api_dir` 目录
- 支持指定版本（tag / branch）
- 自动解析 `module.json` 中的依赖关系并递归安装
- 支持多 Git 平台配置
- 集成 `ProcMgr` 进程管理，实时输出安装日志
- 防止重复安装已存在的模块

## 待开发功能

- [ ] 模块更新（`update` 命令）
- [ ] 模块卸载（`remove` 命令）
- [ ] 已安装模块列表（`list` 命令）

## 安装

模块管理器本身是一个 Nervsys 模块，放置在 `modules/manager/` 目录下。

```
modules/manager/
├── go.php          # 入口文件
├── module.json     # 模块元信息
├── local.json      # 本地配置
└── README.md       # 说明文档
```

### 配置文件 `local.json`

```json
{
  "git_source": "github.com",
  "git_platforms": {
    "github.com": {
      "git_url": "https://github.com/{user}/{repo}.git"
    },
    "gitee.com": {
      "git_url": "https://gitee.com/{user}/{repo}.git"
    },
    "gitlab.com": {
      "git_url": "https://gitlab.com/{user}/{repo}.git"
    }
  }
}
```

- `git_source`：默认使用的 Git 平台（请确保在 `git_platforms` 中存在）
- `git_platforms`：支持的 Git 平台及其 Git 地址模板（使用 `{user}` 和 `{repo}` 作为占位符）

## 使用方法

### 启用模块模式

在入口文件 `www/index.php` 中启用模块模式：

```php
$ns = new Nervsys\NS();
$ns->setMode('module')
   ->setApiDir('modules')
   ->go();
```

### CLI 命令

通过 `index.php` 调用模块管理器：

```bash
# 安装模块（使用默认平台）
php index.php -c"/Nervsys/modules/manager/go/install" -d"user_repo=demo/logger"

# 安装模块并指定版本
php index.php -c"/Nervsys/modules/manager/go/install" -d"user_repo=demo/logger&tag=v1.0.0"
```

### 设置默认 Git 平台

**方法一：通过 CLI 命令设置**

```bash
php index.php -c"/Nervsys/modules/manager/go/setRemote" -d"repo=gitee.com"
```

执行后，配置会自动保存到 `local.json` 文件中。

**方法二：直接编辑配置文件**

修改 `local.json`，添加或更改 `git_source` 字段：

```json
{
  "git_source": "gitee.com",
  "git_platforms": {
    ...
  }
}
```

> 设置的平台必须存在于 `git_platforms` 配置中，否则会报错。

### 模块的 `module.json` 示例

被安装的模块需要包含 `module.json` 文件：

```json
{
  "name": "logger",
  "version": "1.0.0",
  "entry": "go.php",
  "dependencies": {
    "helper": "https://github.com/nervsys/helper.git@v1.0.0"
  }
}
```

- `dependencies` 中的键为模块名，值为 Git 地址（可附带 `#tag` 指定版本）。

## API 方法

### `install(string $user_repo, string $tag = ''): void`

安装模块。

- `$user_repo`：格式 `{user}/{repo}`，例如 `nervsys/logger`
- `$tag`：可选，Git tag 或 branch 名称，默认为空（使用仓库默认分支）

### `setRemote(string $repo): self`

设置默认的 Git 平台。

- `$source`：平台域名或 URL，例如 `github.com` 或 `https://gitee.com`

## 环境要求

- PHP 8.1+
- Git 命令行工具（已添加到 PATH）
- Nervsys 框架（启用模块模式）

## 注意事项

- 首次使用前，请确保 `local.json` 配置文件存在且格式正确
- 模块管理器本身不处理 HTTP 下载，需要通过 Git 命令克隆模块
- 依赖安装是递归的，请确保依赖关系不形成循环（模块管理器会检测已安装模块来避免重复）
- 模块目录必须与 `module.json` 中的 `name` 字段一致

## 许可证

Apache License 2.0