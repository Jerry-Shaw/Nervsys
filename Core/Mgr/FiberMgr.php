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
     * Generate async function from await, or pass a callable function to process await returned result
     * Using "async()->getReturn()" to get async function returned result
     *
     * @param \Fiber        $await_fiber
     * @param callable|null $callable
     *
     * @return \Fiber
     */
    public function async(\Fiber $await_fiber, callable $callable = null): \Fiber
    {
        $async_fiber = new \Fiber(function () use ($await_fiber, $callable): mixed
        {
            while (!$await_fiber->isTerminated()) {
                if ($await_fiber->isSuspended()) {
                    $await_fiber->resume();
                }

                \Fiber::suspend();
            }

            $result = $await_fiber->getReturn();

            if (is_callable($callable)) {
                $result = is_array($result) && !array_is_list($result)
                    ? call_user_func_array($callable, parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $result))
                    : call_user_func($callable, $result);
            }

            unset($await_fiber, $callable);
            return $result;
        });

        $this->child[] = $async_fiber;

        unset($await_fiber, $callable);
        return $async_fiber;
    }

    /**
     * Run main fiber process
     *
     * @return void
     * @throws \Throwable
     */
    public function run(): void
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
            foreach ($this->child as $key => $fiber) {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                }

                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if ($fiber->isTerminated()) {
                    unset($this->child[$key]);
                }
            }
        }

        \Fiber::suspend();
        $this->ready();
    }
}