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

$root = end($_SERVER['argv']);

reset($_SERVER['argv']);

$ns->setRootPath($root);
$ns->setApiDir(isset($_SERVER['argv'][4]) ? $_SERVER['argv'][3] : 'modules');
$ns->setMode(\Nervsys\Core\Lib\App::MODE_MODULE);

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
        $path_array = array_reverse(array_slice($IOData->src_argv, 1));

        if (1 === count($path_array)) {
            $path_array[] = 'modules';
        }

        $IOData->src_input['repo'] = $IOData->src_argv[0];
        $IOData->src_input['root'] = implode(DIRECTORY_SEPARATOR, $path_array);
    }
);

$ns->go();