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

require __DIR__ . '/../NS.php';

$ns = new Nervsys\NS();

$ns->setDebugMode(true)
    ->setApiDir('modules')
    ->setMode(\Nervsys\Core\Lib\App::MODE_MODULE)
    ->go();