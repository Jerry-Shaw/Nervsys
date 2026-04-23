## libCaptcha Description

`libCaptcha` is a captcha image extension that generates various types of authentication codes (numbers, words, math
expressions). It supports rendering these as images and can store the verification hash in Redis or via encrypted
client-side data. This class extends `Factory`.

**Language:** English | [中文文档](./libCaptcha-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constants

### `TYPE_MIX`, `TYPE_NUM`, `TYPE_WORD`, `TYPE_PLUS`, `TYPE_CALC`

Predefined types for captcha generation:

- `TYPE_MIX`: Mixed alphanumeric characters.
- `TYPE_NUM`: Pure numeric characters.
- `TYPE_WORD`: English letter characters.
- `TYPE_PLUS`: Simple addition math expression (e.g., "1+2=3").
- `TYPE_CALC`: More complex calculation expression.

### `KEY_PREFIX`

The prefix used for storing captcha hashes in Redis (`CAPTCHA:`).

## Properties

### `$libCrypt`

- **Type:** `libCrypt`
- **Description:** Crypt object for handling encryption/decryption if not using Redis.

### `$redis`

- **Type:** `\Redis|libRedis`
- **Description:** Redis instance used for storing captcha hashes.

### `$use_redis`

- **Type:** `bool`
- **Description:** Indicates whether Redis is being used for storage.

## Methods

### `bindCrypt(libCrypt $libCrypt): static`

Binds a `libCrypt` object to the class.

- **Parameters:**
    - `$libCrypt`: An instance of `libCrypt`.
- **Returns:** `$this`.

### `bindRedis(\Redis|libRedis $redis): static`

Binds a Redis instance to the class and enables Redis storage mode.

- **Parameters:**
    - `$redis`: An instance of `\Redis` or `libRedis`.
- **Returns:** `$this`.

### `setSize(int $width = 240, int $height = 80): static`

Sets the dimensions for the generated captcha image.

- **Parameters:**
    - `$width`: Width in pixels.
    - `$height`: Height in pixels.
- **Returns:** `$this`.

### `setFont(string $font_file): static`

Sets the font file used for rendering text.

- **Parameters:**
    - `$font_file`: Path to the `.ttf` font file.
- **Returns:** `$this`.

### `setLength(string $length): static`

Sets the length of the generated code (only for `num` and `word` types).

- **Parameters:**
    - `$length`: The number of characters.
- **Returns:** `$this`.

### `setTypes(string ...$type): static`

Sets which captcha types are allowed to be generated.

- **Parameters:**
    - `$type`: One or more type constants (e.g., `self::TYPE_NUM`).
- **Returns:** `$this`.

### `get(int $life = 60): array`

Generates a captcha image and returns the data.

- **Parameters:**
    - `$life`: Expiration time in seconds for the verification hash.
- **Returns:** An array containing:
    - `'char'`: Array of characters used in the code.
    - `'hash'`: The verification hash (to be sent to client).
    - `'image'`: Base64 encoded JPEG image data.
- **Throws:** `\Exception` on error.

### `check(string $hash, string $input): bool`

Verifies the user input against the stored hash.

- **Parameters:**
    - `$hash`: The verification hash received from the client.
    - `$input`: The text entered by the user.
- **Returns:** `bool` (true if correct, false otherwise).

## Usage Example

```php
use Nervsys\Ext\libCaptcha;

$captcha = new libCaptcha();
$captcha->bindRedis($redis); // Use Redis for storage
$captcha->setTypes(libCaptcha::TYPE_NUM);
$captcha->setSize(150, 50);

// Generate captcha
$data = $captcha->get(60);  
// $data['image'] contains the base64 image string
// $data['hash'] is what you should store in a session or hidden field

// Verify user input
$userInput = $_POST['code'];
if ($captcha->check($data['hash'], $userInput)) {
    echo "Verification successful!";
} else {
    echo "Invalid captcha code.";
}
```
