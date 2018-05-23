<?php

/**
 * Observer Handler
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace core\handler;

use core\parser\conf;
use core\parser\input;

class observer
{
    /**
     * Start observer
     */
    public static function start(): void
    {
        //Load config settings
        conf::load();

        //Check CORS permission
        conf::chk_cors();

        //Call INIT setting functions
        conf::call_init();

        //Prepare input data
        input::prep_data();

    }


}