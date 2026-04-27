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

require __DIR__ . '/../../../NS.php';

$ns = new Nervsys\NS();

$ns->setMode(\Nervsys\Core\Lib\App::MODE_MODULE);

if (isset($_SERVER['argv'][3]) && isset($_SERVER['argv'][4])) {
    $ns->setApiDir($_SERVER['argv'][3]);
    $ns->setRootPath($_SERVER['argv'][4]);
} elseif (isset($_SERVER['argv'][3])) {
    $ns->setApiDir('modules');
    $ns->setRootPath($_SERVER['argv'][3]);
}

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
    function (\Nervsys\Core\Lib\IOData $IOData): void
    {
        $argv_repo = $IOData->src_argv[0];

        if (!str_contains($argv_repo, '/')) {
            throw new \InvalidArgumentException('Invalid repository format. Expected "{user}/{repo}" or "{user}/{repo}#{tag}".');
        }

        if (str_contains($argv_repo, '#')) {
            [$user_repo, $tag] = explode('#', $argv_repo);
        } else {
            $user_repo = $argv_repo;
            $tag       = '';
        }

        $IOData->src_input['user_repo'] = $user_repo;
        $IOData->src_input['tag']       = $tag;

        if (isset($IOData->src_argv[3]) && isset($IOData->src_argv[2]) && isset($IOData->src_argv[1])) {
            if (!in_array($IOData->src_argv[2], ['git', 'https'], true)) {
                throw new \InvalidArgumentException('Invalid git clone type. Expected "git" or "https". Leave it blank for https as default.');
            }

            $IOData->src_input['type'] = $IOData->src_argv[2];
            $IOData->src_input['root'] = $IOData->src_argv[3] . DIRECTORY_SEPARATOR . $IOData->src_argv[1];
        } elseif (isset($IOData->src_argv[2]) && isset($IOData->src_argv[1])) {
            $IOData->src_input['root'] = $IOData->src_argv[2] . DIRECTORY_SEPARATOR . $IOData->src_argv[1];
        } elseif (isset($IOData->src_argv[1])) {
            $IOData->src_input['root'] = $IOData->src_argv[1] . DIRECTORY_SEPARATOR . 'modules';
        }
    }
);

$ns->go();