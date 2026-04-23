## libCrypt 描述

`libCrypt` 是一个加密扩展，提供 AES 加密/解密、RSA 密钥对生成、密码哈希和数字签名功能。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libCrypt-en_us.md)

## 命名空间

`Nervsys\Ext`

## 属性

### `$libKeygen`

- **类型:** `libKeygen`
- **描述:** 用于创建加密密钥的密钥生成器对象。

### `$method`

- **类型:** `string`
- **默认值:** `'AES-256-CTR'`
- **描述:** 用于加密的加密方法。

### `$openssl_cnf`

- **类型:** `string`
- **描述:** OpenSSL 配置文件路径。

## 方法

### `bindKeygen(libKeygen $keygen): self`

将密钥生成器对象绑定到该类。

- **参数:**
    - `$keygen`: `libKeygen` 实例。
- **返回:** `$this`.

### `setMethod(string $method): self`

设置加密方法。

- **参数:**
    - `$method`: 加密方法名称（如 `'AES-256-CTR'`）。
- **返回:** `$this`.

### `setOpensslCnfPath(string $file_path): self`

设置 OpenSSL 配置文件路径。

- **参数:**
    - `$file_path`: OpenSSL 配置文件路径。
- **返回:** `$this`.

### `getKey(int $length = 32, int $key_type = libKeygen::KEY_ALL_ALPHANUMERIC): string`

获取指定长度和类型的加密密钥。

- **参数:**
    - `$length`: 密钥长度（位），默认 32。
    - `$key_type`: 密钥字符类型（数字、字母或两者）。
- **返回:** 生成的密钥字符串。

### `getRsaKeys(): array`

生成 RSA 公钥和私钥对。

- **返回:** 包含 `'public'` 和 `'private'` 键的数组。
- **异常:** 失败时抛出 `\Exception`。

### `encrypt(string $string, string $key): string`

使用 AES 加密字符串。

- **参数:**
    - `$string`: 要加密的纯文本。
    - `$key`: 加密密钥。
- **返回:** Base64 URL 编码的加密字符串。

### `decrypt(string $string, string $key): string`

解密 AES 加密的字符串。

- **参数:**
    - `$string`: 加密字符串（Base64 URL 编码）。
    - `$key`: 解密密钥。
- **返回:** 解密后的纯文本。

### `rsaEncrypt(string $string, string $key): string`

使用 RSA 公钥/私钥加密。

- **参数:**
    - `$string`: 要加密的纯文本。
    - `$key`: RSA 密钥（公钥或私钥）。
- **返回:** Base64 URL 编码的加密字符串。
- **异常:** 失败时抛出 `\Exception`。

### `rsaDecrypt(string $string, string $key): string`

使用 RSA 公钥/私钥解密。

- **参数:**
    - `$string`: 加密字符串（Base64 URL 编码）。
    - `$key`: RSA 密钥（私钥或公钥）。
- **返回:** 解密后的纯文本。
- **异常:** 失败时抛出 `\Exception`。

### `checkPasswd(string $input, string $key, string $hash): bool`

检查密码是否匹配存储的哈希值。

- **参数:**
    - `$input`: 要检查的密码。
    - `$key`: 用于哈希的秘密密钥。
    - `$hash`: 存储的密码哈希。
- **返回:** `bool` (匹配为 true)。

### `hashPasswd(string $string, string $key): string`

使用秘密密钥对密码进行哈希处理。

- **参数:**
    - `$string`: 要哈希的密码。
    - `$key`: 秘密密钥（最少 32 个字符）。
- **返回:** 基于 SHA1 的哈希字符串。

### `sign(string $string, string $rsa_key = ''): string`

为数据创建数字签名。

- **参数:**
    - `$string`: 要签名的数据。
    - `$rsa_key`: 可选的 RSA 密钥用于加密。
- **返回:** 签名字符串 (mix.key_encrypted)。
- **异常:** 失败时抛出 `\Exception`。

### `verify(string $string, string $rsa_key = ''): string`

验证数字签名并返回原始数据。

- **参数:**
    - `$string`: 要验证的签名字符串。
    - `$rsa_key`: 可选的 RSA 密钥用于解密。
- **返回:** 原始签名数据，失败时返回空字符串。
- **异常:** 失败时抛出 `\Exception`。

## 使用示例

```php
use Nervsys\Ext\libCrypt;
use Nervsys\Ext\libKeygen;

$crypt = new libCrypt();
$keygen = new libKeygen();
$crypt->bindKeygen($keygen);

// 生成 AES 密钥
$key = $crypt->getKey(32, libKeygen::KEY_ALL_ALPHANUMERIC);

// 加密/解密
$encrypted = $crypt->encrypt('Secret Data', $key);
$decrypted = $crypt->decrypt($encrypted, $key);
echo "Decrypted: {$decrypted}";

// RSA 密钥
$keys = $crypt->getRsaKeys();
$rsaEncrypted = $crypt->rsaEncrypt('Message', $keys['public']);
$rsaDecrypted = $crypt->rsaDecrypt($rsaEncrypted, $keys['private']);

// 密码哈希
$passwordHash = $crypt->hashPasswd('mypassword', 'secretkey');
$isMatch = $crypt->checkPasswd('mypassword', 'secretkey', $passwordHash);

// 数字签名
$signature = $crypt->sign(json_encode(['user' => 123]));
$verifiedData = $crypt->verify($signature);
```
