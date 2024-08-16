<?php

/**
 * Fiber Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;
use Nervsys\Core\Reflect;

class FiberMgr extends Factory
{
    private array $fibers = [];

    /**
     * Await callable function, generate Fiber instance
     * Call "Fiber::suspend()" inside to make it suspended
     *
     * @param callable $callable
     * @param array    $arguments
     *
     * @return \Fiber
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function await(callable $callable, array $arguments = []): \Fiber
    {
        $await_fiber = new \Fiber($callable);

        if (!empty($arguments) && !array_is_list($arguments)) {
            $arguments = parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $arguments);
        }

        $await_fiber->start(...$arguments);

        unset($callable, $arguments);
        return $await_fiber;
    }

    /**
     * Add callable function to async queue
     * Using callback function to catch the results
     * Call "commit()" in the end after "async()"s to run all fibers
     *
     * @param callable      $callable
     * @param array         $arguments
     * @param callable|null $callback
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function async(callable $callable, array $arguments = [], callable|null $callback = null): void
    {
        $await_fiber = $this->await($callable, $arguments);

        if ($await_fiber->isTerminated()) {
            if (is_callable($callback)) {
                $this->fiberDone($await_fiber, $callback);
            }
        } else {
            $this->fibers[] = [&$await_fiber, &$callback];
        }

        unset($callable, $arguments, $callback, $await_fiber);
    }

    /**
     * Commit all async fibers
     *
     * @return void
     * @throws \Throwable
     */
    public function commit(): void
    {
        while (!empty($this->fibers)) {
            foreach ($this->fibers as $fiber_key => $fiber_proc) {
                if ($fiber_proc[0]->isSuspended()) {
                    $fiber_proc[0]->resume();
                }

                if ($fiber_proc[0]->isTerminated()) {
                    unset($this->fibers[$fiber_key]);

                    if (is_callable($fiber_proc[1])) {
                        $this->fiberDone($fiber_proc[0], $fiber_proc[1]);
                    }
                }
            }

            $this->fibers = array_values($this->fibers);

            unset($fiber_key, $fiber_proc);
        }
    }

    /**
     * @param \Fiber   $fiber
     * @param callable $callback
     *
     * @return void
     * @throws \ReflectionException
     */
    private function fiberDone(\Fiber $fiber, callable $callback): void
    {
        $result = $fiber->getReturn();

        is_array($result) && !array_is_list($result)
            ? call_user_func_array($callback, parent::buildArgs(Reflect::getCallable($callback)->getParameters(), $result))
            : call_user_func($callback, $result);

        unset($fiber, $callback, $result);
    }
}