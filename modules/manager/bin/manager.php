<?php

/**
 * Nervsys Module Manager entry script
 *
 * Copyright 2026 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types = 1);

use Nervsys\Core\Lib\App;
use Nervsys\Core\Lib\IOData;

$dir = $_SERVER['argv'][1];
$cwd = array_pop($_SERVER['argv']);
unset($_SERVER['argv'][1]);

$_SERVER['argv'] = array_values($_SERVER['argv']);

if (count($_SERVER['argv']) < 3) {
    echo 'Usage: mm [target_dir] <command> [args]' . PHP_EOL;
    echo 'Example: mm modules install nervsys/logger@v1.0#git' . PHP_EOL;
    exit(1);
}

$_SERVER['argv'][1] = strtr($_SERVER['argv'][1], '-', '_');

if (!in_array($_SERVER['argv'][1], ['install', 'set_remote', 'init'], true)) {
    echo 'Unknown command: ' . $_SERVER['argv'][1] . PHP_EOL;
    exit(1);
}

$repo = $_SERVER['argv'][2];

require __DIR__ . '/../../../NS.php';

$ns = new Nervsys\NS($cwd, $dir, App::MODE_MODULE);

$ns->addCgiRouter(
    function (string $c): array
    {
        return [
            '\\Nervsys\\modules\\manager\\go',
            $c
        ];
    }
);

$ns->addCliHandler(
    function (IOData $IOData) use ($repo): void
    {
        $IOData->src_input['repo'] = $repo;
    }
);

$ns->setOutputHandler(
    function (IOData $IOData)
    {
    }
);

$ns->go();