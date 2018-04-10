<?php

/**
 * API Script
 *
 * Copyright 2016-2018 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

//Check Version
if (version_compare(PHP_VERSION, '7.1.0', '<')) exit('NervSys needs PHP 7.1.0 or higher!');

//Load Basic Config
require __DIR__ . '/core/conf.php';

//Load Router Config
\core\ctr\router::load_conf();

//Load Router CORS
\core\ctr\router::load_cors();

//Run Process
'cli' !== PHP_SAPI ? \core\ctr\router\cgi::run() : \core\ctr\router\cli::run();

//Output Result
\core\ctr\router::output();