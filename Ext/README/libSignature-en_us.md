## libSignature Description

`libSignature` is a signature extension that provides methods for verifying and calculating data signatures using MD5
hashing. It extends `Factory`.

**Language:** English | [中文文档](./libSignature-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Properties

### `$debug_mode`

- **Type:** `bool`
- **Default:** `false`
- **Description:** Enables debug mode to include raw query string in response.

### `$debug_key`

- **Type:** `string`
- **Default:** `'dbg_str'`
- **Description:** Key name used for debug output when debug_mode is enabled.

## Methods

### `setDebugMode(bool $debug_mode): static`

Enables or disables debug mode.

- **Parameters:**
    - `$debug_mode`: Whether to enable debug mode.
- **Returns:** `$this`.

### `setDebugKey(string $key): static`

Sets the key name for debug output.

- **Parameters:**
    - `$key`: Key name to use in response.
- **Returns:** `$this`.

###

`verify(string $app_key, string $app_secret, string $sign, array|null $input_data = null, callable|null $sign_handler = null, callable|null $error_handler = null): bool`

Verifies data signature against server calculation.

- **Parameters:**
    - `$app_key`: Application key.
    - `$app_secret`: Application secret.
    - `$sign`: Signature to verify.
    - `$input_data`: Input data array (optional, defaults to request input).
    - `$sign_handler`: Optional callback for custom signature logic.
    - `$error_handler`: Optional callback for error handling.
- **Returns:** `bool` (true if valid).

### `sign(array $data, string $app_key, string $app_secret, callable|null $sign_handler = null): array`

Calculates signature and adds needed data to source.

- **Parameters:**
    - `$data`: Data array to sign.
    - `$app_key`: Application key.
    - `$app_secret`: Application secret.
    - `$sign_handler`: Optional callback for custom signature logic.
- **Returns:** Array with added signature, appKey, timestamp, and nonceStr.

### `buildQuery(array $data): string`

Builds query string from data without escaping.

- **Parameters:**
    - `$data`: Associative array of parameters.
- **Returns:** Query string (e.g., "key1=value1&key2=value2").

### `filterData(array $data): array`

Filters out array and null values from data.

- **Parameters:**
    - `$data`: Input data array.
- **Returns:** Filtered data array.

## Usage Example

```php
use Nervsys\Ext\libSignature;

$signature = new libSignature();
$signature->setDebugMode(true);

// Verify signature
$inputData = ['user_id' => 123, 'action' => 'create'];
$isValid = $signature->verify(
    'app_key_123', 
    'secret_key_456', 
    $_SERVER['HTTP_SIGNATURE'], 
    $inputData
);

// Calculate signature for request
$signData = ['user_id' => 123, 'action' => 'create'];
$signedData = $signature->sign($signData, 'app_key_123', 'secret_key_456');
```
