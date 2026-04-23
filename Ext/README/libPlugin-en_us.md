## libPlugin Description

`libPlugin` is a plugin management extension that provides methods for loading, unloading, and managing plugins. It
extends `Factory`.

**Language:** English | [中文文档](./libPlugin-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `load(string $plugin_name): bool`

Loads a plugin by name.

- **Parameters:**
    - `$plugin_name`: Name of the plugin to load.
- **Returns:** `bool` (true on success).

### `unload(string $plugin_name): bool`

Unloads a loaded plugin.

- **Parameters:**
    - `$plugin_name`: Name of the plugin to unload.
- **Returns:** `bool` (true on success).

### `isEnabled(string $plugin_name): bool`

Checks if a plugin is currently enabled.

- **Parameters:**
    - `$plugin_name`: Name of the plugin.
- **Returns:** `bool` (true if enabled).

## Usage Example

```php
use Nervsys\Ext\libPlugin;

$plugin = new libPlugin();

// Load a plugin
if ($plugin->load('payment_gateway')) {
    echo "Payment gateway loaded.";
}

// Check if plugin is enabled
if ($plugin->isEnabled('cache_manager')) {
    echo "Cache manager is active.";
}

// Unload a plugin
$plugin->unload('debug_tool');
```
