## libSignature 描述

`libSignature` 是一个签名扩展，提供使用 MD5 哈希验证和计算数据签名的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libSignature-en_us.md)

## 命名空间

`Nervsys\Ext`

## 属性

### `$debug_mode`

- **类型:** `bool`
- **默认值:** `false`
- **描述:** 启用调试模式，在响应中包含原始查询字符串。

### `$debug_key`

- **类型:** `string`
- **默认值:** `'dbg_str'`
- **描述:** 当启用 debug_mode 时用于调试输出的键名。

## 方法

### `setDebugMode(bool $debug_mode): self`

启用或禁用调试模式。

- **参数:**
    - `$debug_mode`: 是否启用调试模式。
- **返回:** `$this`.

### `setDebugKey(string $key): self`

设置调试输出的键名。

- **参数:**
    - `$key`: 响应中使用的键名。
- **返回:** `$this`.

###

`verify(string $app_key, string $app_secret, string $sign, array|null $input_data = null, callable|null $sign_handler = null, callable|null $error_handler = null): bool`

将数据签名与服务器计算进行验证。

- **参数:**
    - `$app_key`: 应用密钥。
    - `$app_secret`: 应用秘密。
    - `$sign`: 要验证的签名。
    - `$input_data`: 输入数据数组（可选，默认为请求输入）。
    - `$sign_handler`: 自定义签名逻辑的可选回调。
    - `$error_handler`: 错误处理的可选回调。
- **返回:** `bool` (有效为 true)。

### `sign(array $data, string $app_key, string $app_secret, callable|null $sign_handler = null): array`

计算签名并将所需数据添加到源中。

- **参数:**
    - `$data`: 要签名的数据数组。
    - `$app_key`: 应用密钥。
    - `$app_secret`: 应用秘密。
    - `$sign_handler`: 自定义签名逻辑的可选回调。
- **返回:** 包含添加的签名、appKey、timestamp 和 nonceStr 的数组。

### `buildQuery(array $data): string`

从数据构建查询字符串（不转义）。

- **参数:**
    - `$data`: 参数的关联数组。
- **返回:** 查询字符串（如 "key1=value1&key2=value2"）。

### `filterData(array $data): array`

过滤掉数据中的数组和 null 值。

- **参数:**
    - `$data`: 输入数据数组。
- **返回:** 过滤后的数据数组。

## 使用示例

```php
use Nervsys\Ext\libSignature;

$signature = new libSignature();
$signature->setDebugMode(true);

// 验证签名
$inputData = ['user_id' => 123, 'action' => 'create'];
$isValid = $signature->verify(
    'app_key_123', 
    'secret_key_456', 
    $_SERVER['HTTP_SIGNATURE'], 
    $inputData
);

// 为请求计算签名
$signData = ['user_id' => 123, 'action' => 'create'];
$signedData = $signature->sign($signData, 'app_key_123', 'secret_key_456');
```
