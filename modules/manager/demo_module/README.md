# Your Module Name

## Quick Start

1. Replace `your_module_name` in `module.json` with your actual module name (must match the module directory name)
2. Rename the module directory to match the `name` field in `module.json`
3. Change the git url and tag to match `repo` field in `module.json`
4. Update the namespace in `go.php` to `modules\{your_module_name}`
5. Write your business logic in `go.php` or create separate class files
6. Add dependencies to `module.json` as needed, update other fields as needed
7. Refer to the [Nervsys Framework Documentation] for advanced usage

## Important Naming Rules

| Item                   | Rule                                 | Example               |
|------------------------|--------------------------------------|-----------------------|
| Module directory       | Same as `name` in `module.json`      | `modules/logger/`     |
| `module.json` → `name` | Lowercase, use underscore for spaces | `logger`, `user_auth` |
| Namespace in `go.php`  | `modules\{module_name}`              | `modules\logger`      |

## File Structure

```
{module_name}/
├── go.php # Entry file (required) - exposes methods to Nervsys
├── module.json # Module metadata (required)
├── README.md # This file
├── app/ # (Optional) Your business logic classes
├── Handler.php
└── ...
```

## Entry File (`go.php`)

Only methods in `go.php` are exposed for Nervsys to call. You can organize other logic in separate classes.

### Basic Template

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;

class go extends Factory
{
    /**
     * Public methods here are callable by Nervsys
     */
    public function yourMethod(string $param): string
    {
        // Call your app logic
        return YourAppClass::process($param);
    }
}
```

## Recommended Pattern: Separate App Logic

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;
use modules\{your_module_name}\app\YourHandler;

class go extends Factory
{
    public function doSomething(array $data): array
    {
        // Delegate to app logic
        return YourHandler::new()->handle($data);
    }
}
```

## Example with Custom App Class (app/YourHandler.php)

```php
<?php

namespace modules\{your_module_name}\app;

use Nervsys\Core\Factory;

class YourHandler extends Factory
{
    public function handle(array $data): array
    {
        // Your business logic here
        return $data;
    }
}
```

## Module Metadata (`module.json`)

Edit the following fields as needed:

- `name`: Module name (must match directory name)
- `version`: Module version (semver recommended)
- `description`: Brief module description
- `author`: Module author
- `entry`: Actual entry filename (could be any file besides `go.php`)
- `repo`: The git repo url of this module
- `dependencies`: Other modules this module depends on
- `requires`: Runtime requirements (e.g., PHP version)
- `other-metadata`: ...

### Metadata Example

```json
{
  "name": "logger",
  "version": "1.0.0",
  "description": "Logger module",
  "author": "Your Name",
  "entry": "go.php",
  "repo": "https://github.com/your/logger@1.0.0",
  "dependencies": {
    "helper": "https://github.com/nervsys/helper.git@2.0.0"
  },
  "requires": {
    "php": ">=8.1"
  }
}
```

## Notes

- The module directory name must match the `name` field in `module.json`
- Only public methods in `go.php` are callable by Nervsys framework
- You can organize your own code structure freely (e.g., app/, lib/, config/)
- This is a starter template. Add your own files and logic as needed
- See Nervsys framework documentation for complete API reference

## License

Apache License 2.0