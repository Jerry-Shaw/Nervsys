## libPDO Description

`libPDO` is a PDO connector class that builds a DSN, sets connection options, and establishes a PDO instance. It extends
`Factory`.

**Language:** English | [中文文档](./libPDO-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Public Properties

### `public \PDO|null $pdo`

The underlying PDO instance. `null` before calling `connect()`.

## Constructor

###
`__construct(string $type = 'mysql', string $host = '127.0.0.1', int $port = 3306, string $user = 'root', string $pwd = '', string $db = '', int $timeout = 10, bool $persist = true, string $charset = 'utf8mb4')`

Creates a new `libPDO` instance and builds the DSN and options, but does **not** connect immediately.

- **Parameters:**
    - `$type`: Database type – `mysql`, `mssql`, `pgsql`, `oci`, or `sqlite`.
    - `$host`: Hostname/IP (for SQLite, this is the file path or `':memory:'`).
    - `$port`: Port number (not used for SQLite).
    - `$user`: Username (not used for SQLite).
    - `$pwd`: Password (not used for SQLite).
    - `$db`: Database name (not used for SQLite).
    - `$timeout`: Connection timeout in seconds (for SQLite, converted to milliseconds for busy timeout).
    - `$persist`: Enable persistent connection.
    - `$charset`: Character set (for SQLite, used in `PRAGMA encoding`; not used for PostgreSQL).

## Methods

### `connect(): static`

Creates the actual PDO connection using the previously built DSN and options. For SQLite, it also enables foreign key
constraints (`PRAGMA foreign_keys = ON`).

- **Returns:** The current instance (fluent interface).

### Usage Example

```php
use Nervsys\Ext\libPDO;

// MySQL
$pdo = new libPDO('mysql', 'localhost', 3306, 'root', 'password', 'my_db');
$pdo->connect();
$dbh = $pdo->pdo; // Now you have the PDO object

// SQLite (in-memory)
$pdo = new libPDO('sqlite', ':memory:');
$pdo->connect();
// Use $pdo->pdo for native PDO operations,
// or pass it to libSQLite/libMySQL for fluent query builder.
```
