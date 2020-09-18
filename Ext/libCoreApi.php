<?php

/**
 * CoreAPI Extension
 *
 * Copyright 2016-2020 take your time <704505144@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\App;
use Core\Lib\CORS;
use Core\Lib\Error;
use Core\Lib\IOUnit;
use Core\Lib\Router;
use Core\Lib\Hook;
/**
 * Class libCoreAPI
 *
 * @package Ext
 */
class libCoreAPI extends Factory
{
    /**
     * Set autoload to target path
     *
     * @param string $path
     *
     * @return $this
     */
    public function autoload(string $path): self
    {
        $path = App::new()->root_path . DIRECTORY_SEPARATOR . $path;

        spl_autoload_register(
            function (string $class) use ($path): void
            {
                //Try to load class file "root_path/$path/namespace/class.php"
                if (is_file($class_file = $path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php')) {
                    require $class_file;
                }

                unset($class, $path, $class_file);
            }
        );

        unset($path);
        return $this;
    }

    /**
     * Generate UUID (string hash based)
     *
     * @param string $string
     *
     * @return string
     */
    public function getUuid(string $string = ''): string
    {
        if ('' === $string) {
            $string = uniqid(microtime() . getmypid() . mt_rand(), true);
        }

        $start  = 0;
        $codes  = [];
        $length = [8, 4, 4, 4, 12];
        $string = hash('md5', $string);

        foreach ($length as $len) {
            $codes[] = substr($string, $start, $len);
            $start   += $len;
        }

        $uuid = implode('-', $codes);

        unset($string, $start, $codes, $length, $len);
        return $uuid;
    }

    /**
     * Set api pathname
     *
     * @param string $pathname
     *
     * @return self
     */
    public function setApiPath(string $pathname): self
    {
        App::new()->setApiPath($pathname);

        unset($pathname);
        return $this;
    }

    /**
     * Set app pathname and root path
     *
     * @param string $pathname
     *
     * @return self
     */
    public function setAppPath(string $pathname): self
    {
        App::new()->setAppPath($pathname);

        unset($pathname);
        return $this;
    }

    /**
     * Set inc pathname
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function setIncPath(string $pathname): self
    {
        App::new()->setIncPath($pathname);

        unset($pathname);
        return $this;
    }

    /**
     * Set default timezone
     *
     * @param string $timezone
     *
     * @return $this
     */
    public function setTimezone(string $timezone): self
    {
        App::new()->setTimezone($timezone);

        unset($timezone);
        return $this;
    }

    /**
     * Set auto_call mode
     *
     * @param bool $auto_call_mode
     *
     * @return $this
     */
    public function setAutoCall(bool $auto_call_mode): self
    {
        App::new()->setAutoCall($auto_call_mode);

        unset($auto_call_mode);
        return $this;
    }

    /**
     * Set core_debug mode
     *
     * @param bool $core_debug_mode
     *
     * @return $this
     */
    public function setCoreDebug(bool $core_debug_mode): self
    {
        App::new()->setCoreDebug($core_debug_mode);

        unset($core_debug_mode);
        return $this;
    }

    /**
     * Show debug message and continue
     *
     * @param \Throwable $throwable
     * @param bool       $show_on_cli
     *
     * @return $this
     */
    public function showDebug(\Throwable $throwable, bool $show_on_cli = false): self
    {
        App::new()->showDebug($throwable, $show_on_cli);

        unset($throwable, $show_on_cli);
        return $this;
    }

    /**
     * Set custom ContentType
     *
     * @param string $content_type
     *
     * @return $this
     */
    public function setContentType(string $content_type): self
    {
        IOUnit::new()->setContentType($content_type);

        unset($content_type);
        return $this;
    }

    /**
     * Set header keys to read
     *
     * @param string ...$keys
     *
     * @return $this
     */
    public function readHeaderKeys(string ...$keys): self
    {
        IOUnit::new()->setHeaderKeys(...$keys);

        unset($keys);
        return $this;
    }

    /**
     * Set cookie keys to read
     *
     * @param string ...$keys
     *
     * @return $this
     */
    public function readCookieKeys(string ...$keys): self
    {
        IOUnit::new()->setCookieKeys(...$keys);

        unset($keys);
        return $this;
    }

    /**
     * Set custom CgiHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setCgiReader(object $handler_object, string $handler_method): self
    {
        IOUnit::new()->setCgiReader($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom CliHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setCliReader(object $handler_object, string $handler_method): self
    {
        IOUnit::new()->setCliReader($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom OutputHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setOutputHandler(object $handler_object, string $handler_method): self
    {
        IOUnit::new()->setOutputHandler($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set error code, NO & Msg
     *
     * @param int    $code
     * @param int    $err_no
     * @param string $err_msg
     *
     * @return $this
     */
    public function setErrorData(int $code, int $err_no, string $err_msg): self
    {
        IOUnit::new()->setErrorData($code, $err_no, $err_msg);

        unset($code, $err_no, $err_msg);
        return $this;
    }

    /**
     * Append error info
     *
     * @param array $err_info
     *
     * @return $this
     */
    public function appendErrorInfo(array $err_info): self
    {
        IOUnit::new()->appendErrorInfo($err_info);

        unset($err_info);
        return $this;
    }

    /**
     * Add CORS record
     *
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function addCorsRecord(string $allow_origin, string $allow_headers = ''): self
    {
        CORS::new()->addRecord($allow_origin, $allow_headers);

        unset($allow_origin, $allow_headers);
        return $this;
    }

    /**
     * Set custom ErrorHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setErrorHandler(object $handler_object, string $handler_method): self
    {
        Error::new()->setErrorHandler($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom ShutdownHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setShutdownHandler(object $handler_object, string $handler_method): self
    {
        Error::new()->setShutdownHandler($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom ExceptionHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setExceptionHandler(object $handler_object, string $handler_method): self
    {
        Error::new()->setExceptionHandler($handler_object, $handler_method);

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Add custom router
     *
     * @param object $router_object
     * @param string $router_method
     * @param string $target_stack
     *
     * @return $this
     */
    public function addRouterStack(object $router_object, string $router_method, string $target_stack = 'cgi'): self
    {
        Router::new()->addStack($router_object, $router_method, $target_stack);
        unset($router_object, $router_method, $target_stack);
        return $this;
    }

    /**
     * Add executable path mapping
     *
     * @param string $name
     * @param string $path
     *
     * @return $this
     */
    public function addCliMapping(string $name, string $path): self
    {
        Router::new()->addMapping($name, $path);

        unset($name, $path);
        return $this;
    }

    /**
     * Open root execute permission
     *
     * @param bool $open_root_exec
     *
     * @return $this
     */
    public function openRootExec(bool $open_root_exec): self
    {
        Router::new()->openRootExec($open_root_exec);

        unset($open_root_exec);
        return $this;
    }

    /**
     * frontHook function to c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     * @param bool   $prepend
     *
     * @return $this
     */
    public function frontHook(string $input_c, string $hook_class, string $hook_method): self
    {

        Hook::new()->prepend[$input_c] ??= [];
        array_unshift(Hook::new()->prepend[$input_c], [$hook_class, $hook_method]);

        unset($input_c, $hook_class, $hook_method, $prepend);
        return $this;
    }

    /**
     * rearHook function to c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     * @param bool   $prepend
     *
     * @return $this
     */
    public function rearHook(string $input_c, string $hook_class, string $hook_method): self
    {

        Hook::new()->append[$input_c][] = [$hook_class, $hook_method];
        unset($input_c, $hook_class, $hook_method, $prepend);
        return $this;
    }
}