## libGit 描述

`libGit` 是一个 Git 操作库，提供仓库管理、分支操作和提交处理的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libGit-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `getBranch(string $repo_path): string`

获取仓库的当前分支名称。

- **参数:**
    - `$repo_path`: Git 仓库路径。
- **返回:** 当前分支名称，失败时返回空字符串。

### `getRemote(string $repo_path, string $branch = ''): string`

获取特定分支或默认远程的 URL。

- **参数:**
    - `$repo_path`: Git 仓库路径。
    - `$branch`: 分支名称（可选）。
- **返回:** 远程 URL，失败时返回空字符串。

### `getRemoteBranch(string $repo_path, string $remote = 'origin'): string`

获取本地分支对应的远程分支名称。

- **参数:**
    - `$repo_path`: Git 仓库路径。
    - `$remote`: 远程名称（默认：`'origin'`）。
- **返回:** 远程分支名称，失败时返回空字符串。

### `getCommit(string $repo_path, int $limit = 1): array`

获取仓库的提交历史。

- **参数:**
    - `$repo_path`: Git 仓库路径。
    - `$limit`: 要检索的提交数（默认：1）。
- **返回:** 包含以下内容的提交对象数组：
    - `'commit'`: 提交哈希。
    - `'author_name'`: 作者名称。
    - `'author_email'`: 作者邮箱。
    - `'date'`: 提交日期。
    - `'message'`: 提交信息。

### `getCommitCount(string $repo_path): int`

获取仓库中的总提交数。

- **参数:**
    - `$repo_path`: Git 仓库路径。
- **返回:** 总提交数，失败时返回 0。

## 使用示例

```php
use Nervsys\Ext\libGit;

$git = new libGit();
$repoPath = '/path/to/repo';

// 获取当前分支
$branch = $git->getBranch($repoPath);
echo "Current branch: {$branch}";

// 获取远程 URL
$remote = $git->getRemote($repoPath, 'main');
echo "Remote URL: {$remote}";

// 获取提交历史
$commits = $git->getCommit($repoPath, 5);
foreach ($commits as $commit) {
    echo "Commit: {$commit['commit']}\n";
    echo "Author: {$commit['author_name']} <{$commit['author_email']}>\n";
    echo "Date: {$commit['date']}\n";
    echo "Message: {$commit['message']}\n\n";
}

// 获取提交数
$count = $git->getCommitCount($repoPath);
echo "Total commits: {$count}";
```
