# Nervsys Module Manager

Module Manager is the official module management tool for the Nervsys framework. It downloads and installs modules from
Git repositories (GitHub / Gitee / GitLab, etc.).

## Features

- Clone modules from Git repositories to the `modules/` directory
- Support specifying version (tag / branch)
- Automatically parse dependencies in `module.json` and install recursively
- Support multiple Git platform configurations
- Integrated with `ProcMgr` for real-time installation log output
- Prevent re-installation of existing modules

## Planned Features

- [ ] Module update (`update` command)
- [ ] Module uninstall (`remove` command)
- [ ] List installed modules (`list` command)

## Installation

The Module Manager itself is a Nervsys module, placed in the `modules/manager/` directory.

```
modules/manager/
├── go.php          # Entry file
├── module.json     # Module metadata
├── local.json      # Local configuration (must be created manually)
└── README.md       # Documentation
```

### Configuration File `local.json`

```json
{
  "git_source": "github.com",
  "git_platforms": {
    "github.com": {
      "git_url": "https://github.com/{user}/{repo}.git"
    },
    "gitee.com": {
      "git_url": "https://gitee.com/{user}/{repo}.git"
    },
    "gitlab.com": {
      "git_url": "https://gitlab.com/{user}/{repo}.git"
    }
  }
}
```

- `git_source`: Default Git platform (must exist in `git_platforms`)
- `git_platforms`: Supported Git platforms with their Git URL templates (using `{user}` and `{repo}` as placeholders)

## Usage

### Enable Module Mode

Enable module mode in the entry file `www/index.php`:

```php
$ns = new Nervsys\NS();
$ns->setMode('module')
   ->setApiDir('modules')
   ->go();
```

### CLI Commands

Call the Module Manager via `index.php`:

```bash
# Install a module (using default platform)
php index.php -c"manager/install" -d'user_repo=nervsys/logger'

# Install a module with a specific version
php index.php -c"manager/install" -d'user_repo=nervsys/logger&tag=v1.0.0'
```

### Set the default Git platform

### Set Default Git Platform

The module manager uses `github.com` as the default Git platform. You can change it in the following two ways:

**Method 1: Via CLI command**

```bash
php index.php -c"/Nervsys/modules/manager/go/setRemote" -d"repo=gitee.com"
```

After execution, the configuration will be automatically saved to the `local.json` file.

**Method 2: Edit the configuration file directly**

Modify `local.json` by adding or changing the `git_source` field:

```json
{
  "git_source": "gitee.com",
  "git_platforms": {
    ...
  }
}
```

> The specified platform must exist in the `git_platforms` configuration; otherwise, an error will be thrown.

### Example `module.json` for a Module

A module to be installed must include a `module.json` file:

```json
{
  "name": "logger",
  "version": "1.0.0",
  "entry": "go.php",
  "dependencies": {
    "helper": "https://github.com/nervsys/helper.git#v1.0.0"
  }
}
```

- The key in `dependencies` is the module name, and the value is the Git URL (optionally with `#tag` to specify a
  version).

## API Methods

### `install(string $user_repo, string $tag = ''): void`

Install a module.

- `$user_repo`: Format `{user}/{repo}`, e.g., `nervsys/logger`
- `$tag`: Optional, Git tag or branch name. Default is empty (uses the repository's default branch)

### `setRemote(string $repo): self`

Set the default Git platform.

- `$source`: Platform domain or URL, e.g., `github.com` or `https://gitee.com`

## Requirements

- PHP 8.1+
- Git command line tool (added to PATH)
- Nervsys framework (with module mode enabled)

## Notes

- Before first use, ensure the `local.json` configuration file exists and has the correct format
- The Module Manager does not handle HTTP downloads; it requires the Git command to clone modules
- Dependency installation is recursive. Make sure there are no circular dependencies (the Module Manager checks for
  already installed modules to avoid duplicates)
- The module directory name must match the `name` field in its `module.json`

## License

Apache License 2.0