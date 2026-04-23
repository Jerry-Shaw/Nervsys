## libCrypt Description

`libCrypt` is a cryptographic extension that provides AES encryption/decryption, RSA key pair generation, password
hashing, and digital signatures. It extends `Factory`.

**Language:** English | [中文文档](./libCrypt-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Properties

### `$libKeygen`

- **Type:** `libKeygen`
- **Description:** Key generator object for creating cryptographic keys.

### `$method`

- **Type:** `string`
- **Default:** `'AES-256-CTR'`
- **Description:** Crypt method used for encryption.

### `$openssl_cnf`

- **Type:** `string`
- **Description:** Path to OpenSSL configuration file.

## Methods

### `bindKeygen(libKeygen $keygen): self`

Binds a key generator object to the class.

- **Parameters:**
    - `$keygen`: An instance of `libKeygen`.
- **Returns:** `$this`.

### `setMethod(string $method): self`

Sets the encryption method.

- **Parameters:**
    - `$method`: Encryption method name (e.g., `'AES-256-CTR'`).
- **Returns:** `$this`.

### `setOpensslCnfPath(string $file_path): self`

Sets the OpenSSL configuration file path.

- **Parameters:**
    - `$file_path`: Path to the OpenSSL config file.
- **Returns:** `$this`.

### `getKey(int $length = 32, int $key_type = libKeygen::KEY_ALL_ALPHANUMERIC): string`

Gets a cryptographic key of specified length and type.

- **Parameters:**
    - `$length`: Key length in bits (default: 32).
    - `$key_type`: Key character type (numbers, letters, or both).
- **Returns:** Generated key string.

### `getRsaKeys(): array`

Generates RSA public and private key pairs.

- **Returns:** Array with `'public'` and `'private'` keys.
- **Throws:** `\Exception` on failure.

### `encrypt(string $string, string $key): string`

Encrypts a string using AES encryption.

- **Parameters:**
    - `$string`: Plain text to encrypt.
    - `$key`: Encryption key.
- **Returns:** Base64 URL-encoded encrypted string.

### `decrypt(string $string, string $key): string`

Decrypts an AES-encrypted string.

- **Parameters:**
    - `$string`: Encrypted string (Base64 URL-encoded).
    - `$key`: Decryption key.
- **Returns:** Decrypted plain text.

### `rsaEncrypt(string $string, string $key): string`

Encrypts using RSA public/private key.

- **Parameters:**
    - `$string`: Plain text to encrypt.
    - `$key`: RSA key (public or private).
- **Returns:** Base64 URL-encoded encrypted string.
- **Throws:** `\Exception` on failure.

### `rsaDecrypt(string $string, string $key): string`

Decrypts using RSA public/private key.

- **Parameters:**
    - `$string`: Encrypted string (Base64 URL-encoded).
    - `$key`: RSA key (private or public).
- **Returns:** Decrypted plain text.
- **Throws:** `\Exception` on failure.

### `checkPasswd(string $input, string $key, string $hash): bool`

Checks if a password matches the stored hash.

- **Parameters:**
    - `$input`: Password to check.
    - `$key`: Secret key for hashing.
    - `$hash`: Stored password hash.
- **Returns:** `bool` (true if match).

### `hashPasswd(string $string, string $key): string`

Hashes a password with a secret key.

- **Parameters:**
    - `$string`: Password to hash.
    - `$key`: Secret key (minimum 32 chars).
- **Returns:** SHA1-based hash string.

### `sign(string $string, string $rsa_key = ''): string`

Creates a digital signature for data.

- **Parameters:**
    - `$string`: Data to sign.
    - `$rsa_key`: Optional RSA key for encryption.
- **Returns:** Signature string (mix.key_encrypted).
- **Throws:** `\Exception` on failure.

### `verify(string $string, string $rsa_key = ''): string`

Verifies a digital signature and returns the original data.

- **Parameters:**
    - `$string`: Signature string to verify.
    - `$rsa_key`: Optional RSA key for decryption.
- **Returns:** Original signed data or empty string on failure.
- **Throws:** `\Exception` on failure.

## Usage Example

```php
use Nervsys\Ext\libCrypt;
use Nervsys\Ext\libKeygen;

$crypt = new libCrypt();
$keygen = new libKeygen();
$crypt->bindKeygen($keygen);

// Generate AES key
$key = $crypt->getKey(32, libKeygen::KEY_ALL_ALPHANUMERIC);

// Encrypt/Decrypt
$encrypted = $crypt->encrypt('Secret Data', $key);
$decrypted = $crypt->decrypt($encrypted, $key);
echo "Decrypted: {$decrypted}";

// RSA Keys
$keys = $crypt->getRsaKeys();
$rsaEncrypted = $crypt->rsaEncrypt('Message', $keys['public']);
$rsaDecrypted = $crypt->rsaDecrypt($rsaEncrypted, $keys['private']);

// Password Hashing
$passwordHash = $crypt->hashPasswd('mypassword', 'secretkey');
$isMatch = $crypt->checkPasswd('mypassword', 'secretkey', $passwordHash);

// Digital Signature
$signature = $crypt->sign(json_encode(['user' => 123]));
$verifiedData = $crypt->verify($signature);
```
