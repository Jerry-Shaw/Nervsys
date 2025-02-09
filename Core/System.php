<?php

/**
 * System Traits
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core;

use Nervsys\Core\Lib\App;
use Nervsys\Core\Lib\Caller;
use Nervsys\Core\Lib\CORS;
use Nervsys\Core\Lib\Error;
use Nervsys\Core\Lib\Hook;
use Nervsys\Core\Lib\IOData;
use Nervsys\Core\Lib\Profiler;
use Nervsys\Core\Lib\Router;
use Nervsys\Core\Lib\Security;
use Nervsys\Core\Mgr\OSMgr;

trait System
{
    public App      $app;
    public CORS     $CORS;
    public Hook     $hook;
    public Error    $error;
    public Caller   $caller;
    public IOData   $IOData;
    public OSMgr    $OSMgr;
    public Router   $router;
    public Security $security;
    public Profiler $profiler;

    public array $autoload_paths = [];

    /**
     * System libraries initializer
     *
     * @return $this
     * @throws \ReflectionException
     */
    public function init(): self
    {
        $this->app      = App::new();
        $this->CORS     = CORS::new();
        $this->hook     = Hook::new();
        $this->error    = Error::new();
        $this->caller   = Caller::new();
        $this->IOData   = IOData::new();
        $this->router   = Router::new();
        $this->OSMgr    = OSMgr::new();
        $this->security = Security::new();
        $this->profiler = Profiler::new();

        return $this;
    }

    /**
     * @return $this
     */
    public function initApp(): self
    {
        register_shutdown_function([$this->error, 'shutdownHandler']);
        set_exception_handler([$this->error, 'exceptionHandler']);
        set_error_handler([$this->error, 'errorHandler']);

        $this->router->cgi_router_stack[] = [$this->router, 'getCgiUnit'];
        $this->router->cli_router_stack[] = [$this->router, 'getCliUnit'];

        $this->security->fn_target_blocked[]   = [$this->security, 'fnTargetBlocked'];
        $this->security->fn_target_invalid[]   = [$this->security, 'fnTargetInvalid'];
        $this->security->fn_argument_invalid[] = [$this->security, 'fnArgumentInvalid'];

        return $this;
    }

    /**
     * @param string $autoload_path
     * @param bool   $autoload_prepend
     *
     * @return $this
     */
    public function addAutoloadPath(string $autoload_path, bool $autoload_prepend = false): self
    {
        $this->autoload_paths[$autoload_path] = static function (string $class) use ($autoload_path): void
        {
            $file_path = $autoload_path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

            if (is_file($file_path)) {
                require $file_path;
            }

            unset($class, $autoload_path, $file_path);
        };

        spl_autoload_register(
            $this->autoload_paths[$autoload_path],
            true,
            $autoload_prepend
        );

        unset($autoload_path, $autoload_prepend);
        return $this;
    }

    /**
     * @param string $autoload_path
     *
     * @return $this
     */
    public function removeAutoloadPath(string $autoload_path): self
    {
        spl_autoload_unregister($this->autoload_paths[$autoload_path]);

        unset($this->autoload_paths[$autoload_path], $autoload_path);
        return $this;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setRootPath(string $path): self
    {
        $this->removeAutoloadPath($this->app->root_path);
        $this->addAutoloadPath($path);

        try {
            $log_path = rtrim($path, '\\/') . DIRECTORY_SEPARATOR . 'logs';
            rename($this->app->log_path, $log_path);
            $this->app->log_path = &$log_path;
        } catch (\Throwable) {
            //Directory exists
        }

        $this->app->root_path = &$path;

        unset($path);
        return $this;
    }

    /**
     * @param string $dir_name
     *
     * @return $this
     */
    public function setApiDir(string $dir_name): self
    {
        $this->app->api_dir = &$dir_name;

        unset($dir_name);
        return $this;
    }

    /**
     * @param string $timezone
     *
     * @return $this
     */
    public function setTimezone(string $timezone): self
    {
        $this->app->timezone = &$timezone;

        unset($timezone);
        return $this;
    }

    /**
     * @param bool $debug_mode
     *
     * @return $this
     */
    public function setDebugMode(bool $debug_mode): self
    {
        $this->app->debug_mode = &$debug_mode;

        unset($debug_mode);
        return $this;
    }

    /**
     * @param string $allow_origin
     * @param string $allow_headers
     * @param string $expose_headers
     *
     * @return $this
     */
    public function addCorsRule(string $allow_origin, string $allow_headers = '', string $expose_headers = ''): self
    {
        $this->CORS->addRule($allow_origin, $allow_headers, $expose_headers);

        unset($allow_origin, $allow_headers, $expose_headers);
        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function addErrorHandler(callable $handler): self
    {
        $this->error->custom_handler[] = $handler;

        unset($handler);
        return $this;
    }

    /**
     * @param callable $handler
     *
     * @return $this
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->error->custom_handler = [$handler];

        unset($handler);
        return $this;
    }

    /**
     * @return $this
     */
    public function resetErrorHandler(): self
    {
        $this->error->custom_handler = [];

        return $this;
    }

    /**
     * @param string   $cmd_path
     * @param callable ...$hook_fn
     *
     * @return $this
     */
    public function addPreHooks(string $cmd_path, callable ...$hook_fn): self
    {
        foreach ($hook_fn as $fn) {
            $this->hook->stack_before[$this->router->getFullCgiCmd($this->app->api_dir, $cmd_path, true)][] = $fn;
        }

        unset($cmd_path, $hook_fn, $fn);
        return $this;
    }

    /**
     * @param callable $hook_fn
     * @param string   ...$cmd_path
     *
     * @return $this
     */
    public function addPreHookRules(callable $hook_fn, string ...$cmd_path): self
    {
        foreach ($cmd_path as $path) {
            $this->hook->stack_before[$this->router->getFullCgiCmd($this->app->api_dir, $path, true)][] = $hook_fn;
        }

        unset($hook_fn, $cmd_path, $path);
        return $this;
    }

    /**
     * @param string   $cmd_path
     * @param callable ...$hook_fn
     *
     * @return $this
     */
    public function addPostHooks(string $cmd_path, callable ...$hook_fn): self
    {
        foreach ($hook_fn as $fn) {
            $this->hook->stack_after[$this->router->getFullCgiCmd($this->app->api_dir, $cmd_path, true)][] = $fn;
        }

        unset($cmd_path, $hook_fn, $fn);
        return $this;
    }

    /**
     * @param callable $hook_fn
     * @param string   ...$cmd_path
     *
     * @return $this
     */
    public function addPostHookRules(callable $hook_fn, string ...$cmd_path): self
    {
        foreach ($cmd_path as $path) {
            $this->hook->stack_after[$this->router->getFullCgiCmd($this->app->api_dir, $path, true)][] = $hook_fn;
        }

        unset($hook_fn, $cmd_path, $path);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function readHeaderKeys(string ...$keys): self
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
    public function readCookieKeys(string ...$keys): self
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
    public function addCgiHandler(callable $cgi_handler): self
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
    public function addCliHandler(callable $cli_handler): self
    {
        $this->IOData->cli_handler[] = $cli_handler;

        unset($cli_handler);
        return $this;
    }

    /**
     * @param string $content_type
     *
     * @return $this
     */
    public function setContentType(string $content_type): self
    {
        $this->IOData->content_type = &$content_type;

        unset($content_type);
        return $this;
    }

    /**
     * @param callable $output_handler
     *
     * @return $this
     */
    public function setOutputHandler(callable $output_handler): self
    {
        $this->IOData->output_handler = [$output_handler];

        unset($output_handler);
        return $this;
    }

    /**
     * @param int    $code
     * @param string $message
     * @param array  $values
     *
     * @return $this
     */
    public function setOutputCodeMsg(int $code, string $message, array $values = []): self
    {
        if (!empty($values)) {
            $message = sprintf($message, ...$values);
        }

        $this->IOData->src_msg['code']    = &$code;
        $this->IOData->src_msg['message'] = &$message;

        unset($code, $message, $values);
        return $this;
    }

    /**
     * @param int $memory_bytes
     * @param int $time_milliseconds
     *
     * @return $this
     */
    public function setProfilerThresholds(int $memory_bytes, int $time_milliseconds): self
    {
        $this->profiler->setThresholds($memory_bytes, $time_milliseconds);

        unset($memory_bytes, $time_milliseconds);
        return $this;
    }

    /**
     * @param callable $cgi_router
     *
     * @return $this
     */
    public function addCgiRouter(callable $cgi_router): self
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
    public function addCliRouter(callable $cli_router): self
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
    public function addCliPathMap(string $exe_name, string $exe_path): self
    {
        $this->router->exe_path_mapping[$exe_name] = &$exe_path;

        unset($exe_name, $exe_path);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function addXssSkipKeys(string ...$keys): self
    {
        $this->security->xss_skip_keys = array_merge($this->security->xss_skip_keys, $keys);

        unset($keys);
        return $this;
    }

    /**
     * @param callable $fnTargetBlocked
     *
     * @return $this
     */
    public function setFnTargetBlocked(callable $fnTargetBlocked): self
    {
        $this->security->fn_target_blocked = [$fnTargetBlocked];

        unset($keys);
        return $this;
    }

    /**
     * @param callable $fnTargetInvalid
     *
     * @return $this
     */
    public function setFnTargetInvalid(callable $fnTargetInvalid): self
    {
        $this->security->fn_target_invalid = [$fnTargetInvalid];

        unset($keys);
        return $this;
    }

    /**
     * @param callable $fnArgumentInvalid
     *
     * @return $this
     */
    public function setFnArgumentInvalid(callable $fnArgumentInvalid): self
    {
        $this->security->fn_argument_invalid = [$fnArgumentInvalid];

        unset($keys);
        return $this;
    }
}