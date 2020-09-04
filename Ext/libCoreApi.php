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
use Core\Lib\IOUnit;
use Core\Lib\Error;
use Core\Lib\Router;
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
     */
    public static function autoLoad(string $path): void
    {
        $path = App::new()->root_path . '/' . $path;

        spl_autoload_register(
            static function (string $class) use ($path): void
            {
                //Try to load class file "ROOT/$path/namespace/class.php"
                if (is_file($class_file = $path . DIRECTORY_SEPARATOR . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php')) {
                    require $class_file;
                }

                unset($class, $path, $class_file);
            }
        );

        unset($path);
    }
    /**
     * App start
     */
    /**
     * Set api pathname
     *
     * @param string $pathname
     * @return self
     */
    public function setApiPath(string $pathname): self
    {
        App::new()->setApiPath($pathname);
        return $this;
    }
    /**
     * Set app pathname and root path
     *
     * @param string $pathname
     * @return self
     */
    public function setAppPath(string $pathname): self
    {
        App::new()->setAppPath($pathname);
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
        return $this;
    }

    /**
     * App end
     */
    /**
     * IOUnit start 
     */
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
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function setHeaderKeys(string ...$keys): self
    {
        IOUnit::new()->setHeaderKeys(...$keys);
        return $this;
    }

    /**
     * @param string ...$keys
     *
     * @return $this
     */
    public function setCookieKeys(string ...$keys): self
    {
        IOUnit::new()->setCookieKeys(...$keys);
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
        IOUnit::new()->setCgiReader($handler_object,$handler_method);
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
        IOUnit::new()->setCliReader($handler_object,$handler_method);
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
        return $this;
    }

    /**
     * Set error No & Msg
     *
     * @param int    $err_no
     * @param string $err_msg
     *
     * @return $this
     */
    public function setErrorNo(int $err_no, string $err_msg): self
    {
        IOUnit::new()->setErrorNo($err_no, $err_msg);
        return $this;
    }
    /**
     * IOUnit end
     */
    /**
     * CORS start
     */
    /**
     * Add CORS record
     *
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function addRecord(string $allow_origin, string $allow_headers = ''): self
    {
        CORS::new()->addRecord($allow_origin,$allow_headers);
        return $this;
    }

    /**
     * Check CORS permission
     *
     * @param \Core\Lib\App $app
     */
    public function checkPerm(App $app): void
    {
        CORS::new()->checkPerm($app);
    }
    /**
     * ERROR
     */
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
        Error::new()->setErrorHandler($handler_object,$handler_method);
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
        Error::new()->setShutdownHandler($handler_object,$handler_method);
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
        Error::new()->setExceptionHandler($handler_object,$handler_method);
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
            //Create random string
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
}
