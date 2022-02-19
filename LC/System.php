<?php

/**
 * System Controller library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\LC;

use Nervsys\Lib\App;
use Nervsys\Lib\CORS;
use Nervsys\Lib\IOData;
use Nervsys\Lib\Router;

class System extends Factory
{
    public App    $app;
    public CORS   $CORS;
    public Caller $caller;
    public IOData $IOData;
    public Router $router;

    /**
     * System constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        Error::new();

        $this->app    = App::new();
        $this->CORS   = CORS::new();
        $this->caller = Caller::new();
        $this->IOData = IOData::new();
        $this->router = Router::new();
    }

    /**
     * @param string $autoload_path
     * @param bool   $autoload_prepend
     *
     * @return $this
     */
    public function addAutoloadPath(string $autoload_path, bool $autoload_prepend = false): self
    {
        spl_autoload_register(
            static function (string $class) use ($autoload_path): void
            {
                $file_path = $autoload_path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

                if (is_file($file_path)) {
                    require $file_path;
                }

                unset($class, $autoload_path, $file_path);
            },
            true,
            $autoload_prepend
        );

        unset($autoload_path, $autoload_prepend);
        return $this;
    }

    /**
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function CorsAddRecord(string $allow_origin, string $allow_headers = ''): self
    {
        $this->CORS->addRecord($allow_origin, $allow_headers);

        unset($allow_origin, $allow_headers);
        return $this;
    }

    /**
     * @param string $pathname
     *
     * @return $this
     */
    public function AppSetApiPath(string $pathname): self
    {
        $this->app->api_path = &$pathname;

        unset($pathname);
        return $this;
    }

    /**
     * @param string $timezone
     *
     * @return $this
     */
    public function AppSetTimezone(string $timezone): self
    {
        $this->app->timezone = &$timezone;

        unset($timezone);
        return $this;
    }

    /**
     * @param bool $core_debug_mode
     *
     * @return $this
     */
    public function AppSetCoreDebug(bool $core_debug_mode): self
    {
        $this->app->core_debug = &$core_debug_mode;

        unset($core_debug_mode);
        return $this;
    }

    /**
     * @param string $content_type
     *
     * @return $this
     */
    public function IODataSetContentType(string $content_type): self
    {
        $this->IOData->content_type = &$content_type;

        unset($content_type);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function IODataReadHeaderKeys(string ...$keys): self
    {
        $this->IOData->header_keys = &$keys;

        unset($keys);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function IODataReadCookieKeys(string ...$keys): self
    {
        $this->IOData->cookie_keys = &$keys;

        unset($keys);
        return $this;
    }

    /**
     * @param callable $cgi_handler
     *
     * @return $this
     */
    public function IODataAddCgiHandler(callable $cgi_handler): self
    {
        $this->IOData->cgi_handler[] = $cgi_handler;

        unset($cgi_handler);
        return $this;
    }

    /**
     * @param callable $cli_handler
     *
     * @return $this
     */
    public function IODataAddCliHandler(callable $cli_handler): self
    {
        $this->IOData->cli_handler[] = $cli_handler;

        unset($cli_handler);
        return $this;
    }

    /**
     * @param callable $output_handler
     *
     * @return $this
     */
    public function IODataSetOutputHandler(callable $output_handler): self
    {
        $this->IOData->output_handler = [$output_handler];

        unset($output_handler);
        return $this;
    }

    /**
     * @param int    $code
     * @param string $msg
     *
     * @return $this
     */
    public function IODataSetCodeMsg(int $code, string $msg): self
    {
        $this->IOData->src_msg['code'] = &$code;
        $this->IOData->src_msg['msg']  = &$msg;

        unset($code, $msg);
        return $this;
    }

    /**
     * @param string $msg_key
     *
     * @return $this
     */
    public function IODataAddMsgData(string $msg_key): self
    {
        $this->IOData->src_msg[$msg_key] = array_merge($this->IOData->src_msg[$msg_key] ?? [], array_slice(func_get_args(), 1, null, true));

        unset($msg_key);
        return $this;
    }

    /**
     * @param callable $cgi_router
     *
     * @return $this
     */
    public function RouterAddCgi(callable $cgi_router): self
    {
        array_unshift($this->router->cgi_router_stack, $cgi_router);

        unset($cgi_router);
        return $this;
    }

    /**
     * @param callable $cli_router
     *
     * @return $this
     */
    public function RouterAddCli(callable $cli_router): self
    {
        array_unshift($this->router->cli_router_stack, $cli_router);

        unset($cli_router);
        return $this;
    }

    /**
     * @param string $exe_name
     * @param string $exe_path
     *
     * @return $this
     */
    public function RouterAddPathMap(string $exe_name, string $exe_path): self
    {
        $this->router->cli_exe_path_map[$exe_name] = &$exe_path;

        unset($exe_name, $exe_path);
        return $this;
    }
}