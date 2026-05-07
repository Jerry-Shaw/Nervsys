# Your Module Name

## Quick Start

1. Replace `your_module_name` in `module.json` with your actual module name (must match the module directory name)
2. Rename the module directory to match the `name` field in `module.json`
3. Update the namespace in `go.php` to `modules\{your_module_name}`
4. Write your business logic in `go.php` or create separate class files
5. Add dependencies to `module.json` as needed
6. Refer to the [Nervsys Framework Documentation] for advanced usage

## Important Naming Rules

| Item                   | Rule                                     | Example               |
|------------------------|------------------------------------------|-----------------------|
| Module directory       | Must match `name` in `module.json`       | `modules/logger/`     |
| `module.json` → `name` | Lowercase, use underscores for spaces    | `logger`, `user_auth` |
| Namespace in `go.php`  | `modules\{module_name}`                  | `modules\logger`      |

## File Structure

```
{module_name}/
├── go.php          # Entry file (required) – exposes methods to Nervsys
├── module.json     # Module metadata (required)
├── README.md       # This file
└── app/            # (Optional) Directory for business logic classes
    ├── Handler.php
    └── ...
```

## Entry File (`go.php`)

Only public methods in `go.php` are callable by Nervsys. It is recommended to delegate concrete logic to separate classes.

### Basic Template

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;

class go extends Factory
{
    /**
     * Public methods are directly callable by Nervsys
     */
    public function yourMethod(string $param): string
    {
        // Call your application logic
        return YourAppClass::process($param);
    }
}
```

### Recommended Pattern: Separate Application Logic

```php
<?php

namespace modules\{your_module_name};

use Nervsys\Core\Factory;
use modules\{your_module_name}\app\YourHandler;

class go extends Factory
{
    public function doSomething(array $data): array
    {
        // Delegate to a dedicated handler
        return YourHandler::new()->handle($data);
    }
}
```

### Custom App Class Example (`app/YourHandler.php`)

```php
<?php

namespace modules\{your_module_name}\app;

use Nervsys\Core\Factory;

class YourHandler extends Factory
{
    public function handle(array $data): array
    {
        // Implement your business logic here
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
- `entry`: Entry file name (default `go.php`, can be any file)
- `repo`: Git repository URL of this module
- `dependencies`: Other modules this module depends on
- `requires`: Runtime requirements (e.g., PHP version)
- Any other custom fields...

### Example Configuration

```json
{
  "name": "logger",
  "version": "1.0.0",
  "description": "Logging utility",
  "author": "Your Name",
  "entry": "go.php",
  "repo": "https://github.com/your/logger",
  "dependencies": {
    "helper": "https://github.com/nervsys/helper.git"
  },
  "requires": {
    "php": ">=8.1"
  }
}
```

## Notes

- The module directory name must strictly match the `name` field in `module.json`
- Only public methods in `go.php` are exposed to the Nervsys framework
- You are free to organise your internal code structure (e.g., `app/`, `lib/`, `config/`)
- This is a starter template; add your own files and logic as needed
- See the Nervsys framework documentation for complete API reference

## License

Apache License 2.0