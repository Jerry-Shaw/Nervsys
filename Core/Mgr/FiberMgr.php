<?php

/**
 * Fiber Manager library
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;
use Nervsys\Core\Reflect;

class FiberMgr extends Factory
{
    private array $fibers = [];

    /**
     * Await callable function, generate Fiber instance
     * MUST call "Fiber::suspend()" inside callable function to make it async
     *
     * @param callable $callable
     * @param array    $args
     *
     * @return \Fiber
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function await(callable $callable, array $args = []): \Fiber
    {
        $await_fiber = new \Fiber($callable);

        if (!empty($args) && !array_is_list($args)) {
            $args = parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $args);
        }

        $await_fiber->start(...$args);

        unset($callable, $args);
        return $await_fiber;
    }

    /**
     * Add await fiber into async child list, pass a callable function to process returned result
     * "commit()" MUST be called in the end after "async()"s, otherwise, async fibers might NOT resume
     *
     * @param \Fiber        $await_fiber
     * @param callable|null $callable
     *
     * @return void
     * @throws \ReflectionException
     */
    public function async(\Fiber $await_fiber, callable $callable = null): void
    {
        if ($await_fiber->isTerminated()) {
            is_callable($callable) && $this->fiberDone($await_fiber, $callable);
        } else {
            $this->fibers[] = [&$await_fiber, &$callable];
        }

        unset($await_fiber, $callable);
    }

    /**
     * Run main fiber process
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

            unset($fiber_key, $fiber_proc);
        }
    }

    /**
     * @param \Fiber   $fiber
     * @param callable $callable
     *
     * @return void
     * @throws \ReflectionException
     */
    private function fiberDone(\Fiber $fiber, callable $callable): void
    {
        $result = $fiber->getReturn();

        is_array($result) && !array_is_list($result)
            ? call_user_func_array($callable, parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $result))
            : call_user_func($callable, $result);

        unset($fiber, $callable, $result);
    }
}