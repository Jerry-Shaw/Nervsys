## libGit Description

`libGit` is a Git operation library that provides methods for repository management, branch operations, and commit
handling. It extends `Factory`.

**Language:** English | [中文文档](./libGit-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `getBranch(string $repo_path): string`

Gets the current branch name of a repository.

- **Parameters:**
    - `$repo_path`: Path to the Git repository.
- **Returns:** Current branch name or empty string on failure.

### `getRemote(string $repo_path, string $branch = ''): string`

Gets the remote URL for a specific branch or default remote.

- **Parameters:**
    - `$repo_path`: Path to the Git repository.
    - `$branch`: Branch name (optional).
- **Returns:** Remote URL or empty string on failure.

### `getRemoteBranch(string $repo_path, string $remote = 'origin'): string`

Gets the remote branch name for a local branch.

- **Parameters:**
    - `$repo_path`: Path to the Git repository.
    - `$remote`: Remote name (default: `'origin'`).
- **Returns:** Remote branch name or empty string on failure.

### `getCommit(string $repo_path, int $limit = 1): array`

Gets commit history for a repository.

- **Parameters:**
    - `$repo_path`: Path to the Git repository.
    - `$limit`: Number of commits to retrieve (default: 1).
- **Returns:** Array of commit objects with:
    - `'commit'`: Commit hash.
    - `'author_name'`: Author name.
    - `'author_email'`: Author email.
    - `'date'`: Commit date.
    - `'message'`: Commit message.

### `getCommitCount(string $repo_path): int`

Gets the total number of commits in a repository.

- **Parameters:**
    - `$repo_path`: Path to the Git repository.
- **Returns:** Total commit count or 0 on failure.

## Usage Example

```php
use Nervsys\Ext\libGit;

$git = new libGit();
$repoPath = '/path/to/repo';

// Get current branch
$branch = $git->getBranch($repoPath);
echo "Current branch: {$branch}";

// Get remote URL
$remote = $git->getRemote($repoPath, 'main');
echo "Remote URL: {$remote}";

// Get commit history
$commits = $git->getCommit($repoPath, 5);
foreach ($commits as $commit) {
    echo "Commit: {$commit['commit']}\n";
    echo "Author: {$commit['author_name']} <{$commit['author_email']}>\n";
    echo "Date: {$commit['date']}\n";
    echo "Message: {$commit['message']}\n\n";
}

// Get commit count
$count = $git->getCommitCount($repoPath);
echo "Total commits: {$count}";
```
