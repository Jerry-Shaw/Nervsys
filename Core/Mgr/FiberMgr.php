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
    private \Fiber $fiber;
    private array  $child = [];

    /**
     * FiberMgr constructor.
     *
     * @throws \Throwable
     */
    public function __construct()
    {
        $this->fiber = new \Fiber([$this, 'ready']);
        $this->fiber->start();
    }

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
     */
    public function async(\Fiber $await_fiber, callable $callable = null): void
    {
        $this->child[] = [$await_fiber, $callable];
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
        $this->fiber->isSuspended() && $this->fiber->resume();
    }

    /**
     * Main fiber ready function
     *
     * @return void
     * @throws \Throwable
     */
    private function ready(): void
    {
        while (!empty($this->child)) {
            foreach ($this->child as $fiber_key => $fiber_proc) {
                if ($fiber_proc[0]->isSuspended()) {
                    $fiber_proc[0]->resume();
                }

                if ($fiber_proc[0]->isTerminated()) {
                    unset($this->child[$fiber_key]);

                    if (is_callable($fiber_proc[1])) {
                        $result = $fiber_proc[0]->getReturn();

                        is_array($result) && !array_is_list($result)
                            ? call_user_func_array($fiber_proc[1], parent::buildArgs(Reflect::getCallable($fiber_proc[1])->getParameters(), $result))
                            : call_user_func($fiber_proc[1], $result);

                        unset($result);
                    }
                }
            }

            unset($fiber_key, $fiber_proc);
        }

        \Fiber::suspend();

        $this->ready();
    }
}