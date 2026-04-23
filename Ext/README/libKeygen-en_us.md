## libKeygen Description

`libKeygen` is a key generator extension that creates random strings for cryptographic keys, passwords, and tokens. It
extends `Factory`.

**Language:** English | [中文文档](./libKeygen-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constants

### `KEY_NUM`, `KEY_ALPHA`, `KEY_ALL_ALPHANUMERIC`

Character type constants:

- `KEY_NUM`: Numeric characters only (0-9).
- `KEY_ALPHA`: Alphabetic characters only (a-z, A-Z).
- `KEY_ALL_ALPHANUMERIC`: All alphanumeric characters.

## Methods

### `getKey(int $length = 32, int $key_type = libKeygen::KEY_ALL_ALPHANUMERIC): string`

Generates a random key of specified length and type.

- **Parameters:**
    - `$length`: Key length in bits (default: 32).
    - `$key_type`: Character type constant.
- **Returns:** Generated key string.

### `getPassword(int $length = 16): string`

Generates a random password with mixed characters.

- **Parameters:**
    - `$length`: Password length (default: 16).
- **Returns:** Random password string.

## Usage Example

```php
use Nervsys\Ext\libKeygen;

$keygen = new libKeygen();

// Generate random key
$apiKey = $keygen->getKey(32, libKeygen::KEY_ALL_ALPHANUMERIC);
echo "API Key: {$apiKey}";

// Generate numeric code
$code = $keygen->getKey(6, libKeygen::KEY_NUM);
echo "Verification Code: {$code}";

// Generate password
$password = $keygen->getPassword(20);
echo "Generated Password: {$password}";
```
