## libPlugin 描述

`libPlugin` 是一个插件管理扩展，提供加载、卸载和管理插件的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libPlugin-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `load(string $plugin_name): bool`

通过名称加载插件。

- **参数:**
    - `$plugin_name`: 要加载的插件名称。
- **返回:** `bool` (成功为 true)。

### `unload(string $plugin_name): bool`

卸载已加载的插件。

- **参数:**
    - `$plugin_name`: 要卸载的插件名称。
- **返回:** `bool` (成功为 true)。

### `isEnabled(string $plugin_name): bool`

检查插件是否当前启用。

- **参数:**
    - `$plugin_name`: 插件名称。
- **返回:** `bool` (如果启用则为 true)。

## 使用示例

```php
use Nervsys\Ext\libPlugin;

$plugin = new libPlugin();

// 加载插件
if ($plugin->load('payment_gateway')) {
    echo "Payment gateway loaded.";
}

// 检查插件是否启用
if ($plugin->isEnabled('cache_manager')) {
    echo "Cache manager is active.";
}

// 卸载插件
$plugin->unload('debug_tool');
```
