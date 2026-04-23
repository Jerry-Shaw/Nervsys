## libKeygen 描述

`libKeygen` 是一个密钥生成器扩展，用于为加密密钥、密码和令牌创建随机字符串。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libKeygen-en_us.md)

## 命名空间

`Nervsys\Ext`

## 常量

### `KEY_NUM`, `KEY_ALPHA`, `KEY_ALL_ALPHANUMERIC`

字符类型常量：

- `KEY_NUM`: 仅数字字符（0-9）。
- `KEY_ALPHA`: 仅字母字符（a-z, A-Z）。
- `KEY_ALL_ALPHANUMERIC`: 所有字母数字字符。

## 方法

### `getKey(int $length = 32, int $key_type = libKeygen::KEY_ALL_ALPHANUMERIC): string`

生成指定长度和类型的随机密钥。

- **参数:**
    - `$length`: 密钥长度（位），默认 32。
    - `$key_type`: 字符类型常量。
- **返回:** 生成的密钥字符串。

### `getPassword(int $length = 16): string`

生成包含混合字符的随机密码。

- **参数:**
    - `$length`: 密码长度（默认：16）。
- **返回:** 随机密码字符串。

## 使用示例

```php
use Nervsys\Ext\libKeygen;

$keygen = new libKeygen();

// 生成随机密钥
$apiKey = $keygen->getKey(32, libKeygen::KEY_ALL_ALPHANUMERIC);
echo "API Key: {$apiKey}";

// 生成数字代码
$code = $keygen->getKey(6, libKeygen::KEY_NUM);
echo "Verification Code: {$code}";

// 生成密码
$password = $keygen->getPassword(20);
echo "Generated Password: {$password}";
```
