## libCaptcha 描述

`libCaptcha` 是一个验证码图片扩展，用于生成各种类型的认证码（数字、单词、数学表达式）。支持将这些内容渲染为图片，并可将验证哈希存储在
Redis 或通过加密的客户端数据存储。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libCaptcha-en_us.md)

## 命名空间

`Nervsys\Ext`

## 常量

### `TYPE_MIX`, `TYPE_NUM`, `TYPE_WORD`, `TYPE_PLUS`, `TYPE_CALC`

预定义的验证码类型：

- `TYPE_MIX`: 混合字母和数字字符。
- `TYPE_NUM`: 纯数字字符。
- `TYPE_WORD`: 英文字母字符。
- `TYPE_PLUS`: 简单加法数学表达式（如 "1+2=3"）。
- `TYPE_CALC`: 更复杂的计算表达式。

### `KEY_PREFIX`

用于在 Redis 中存储验证码哈希的前缀 (`CAPTCHA:`)。

## 属性

### `$libCrypt`

- **类型:** `libCrypt`
- **描述:** 如果不使用 Redis，则用于处理加密/解密的 Crypt 对象。

### `$redis`

- **类型:** `\Redis|libRedis`
- **描述:** 用于存储验证码哈希的 Redis 实例。

### `$use_redis`

- **类型:** `bool`
- **描述:** 指示是否正在使用 Redis 进行存储。

## 方法

### `bindCrypt(libCrypt $libCrypt): self`

将 `libCrypt` 对象绑定到该类。

- **参数:**
    - `$libCrypt`: `libCrypt` 实例。
- **返回:** `$this`.

### `bindRedis(\Redis|libRedis $redis): self`

将 Redis 实例绑定到该类并启用 Redis 存储模式。

- **参数:**
    - `$redis`: `\Redis` 或 `libRedis` 实例。
- **返回:** `$this`.

### `setSize(int $width = 240, int $height = 80): self`

设置生成的验证码图片的尺寸。

- **参数:**
    - `$width`: 宽度（像素）。
    - `$height`: 高度（像素）。
- **返回:** `$this`.

### `setFont(string $font_file): self`

设置用于渲染文本的字体文件。

- **参数:**
    - `$font_file`: `.ttf` 字体文件路径。
- **返回:** `$this`.

### `setLength(string $length): self`

设置生成的代码长度（仅适用于 `num` 和 `word` 类型）。

- **参数:**
    - `$length`: 字符数量。
- **返回:** `$this`.

### `setTypes(string ...$type): self`

设置允许生成的验证码类型。

- **参数:**
    - `$type`: 一个或多个类型常量（如 `self::TYPE_NUM`）。
- **返回:** `$this`.

### `get(int $life = 60): array`

生成验证码图片并返回数据。

- **参数:**
    - `$life`: 验证哈希的过期时间（秒）。
- **返回:** 包含以下内容的数组：
    - `'char'`: 代码中使用的字符数组。
    - `'hash'`: 验证哈希（发送给客户端）。
    - `'image'`: Base64 编码的 JPEG 图片数据。
- **异常:** 错误时抛出 `\Exception`。

### `check(string $hash, string $input): bool`

将用户输入与存储的哈希进行验证。

- **参数:**
    - `$hash`: 从客户端收到的验证哈希。
    - `$input`: 用户输入的文本。
- **返回:** `bool` (正确为 true，否则为 false)。

## 使用示例

```php
use Nervsys\Ext\libCaptcha;

$captcha = new libCaptcha();
$captcha->bindRedis($redis); // 使用 Redis 存储
$captcha->setTypes(libCaptcha::TYPE_NUM);
$captcha->setSize(150, 50);

// 生成验证码
$data = $captcha->get(60);  
// $data['image'] 包含 base64 图片字符串
// $data['hash'] 是你应该存储在 session 或隐藏字段中的值

// 验证用户输入
$userInput = $_POST['code'];
if ($captcha->check($data['hash'], $userInput)) {
    echo "验证成功!";
} else {
    echo "验证码无效。";
}
```
