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
        $fiber = new \Fiber($callable);

        if (!empty($args) && !array_is_list($args)) {
            $args = parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $args);
        }

        $fiber->start(...$args);

        unset($callable, $args);
        return $fiber;
    }

    /**
     * Generate async function from await, or pass a callable function to process await returned result
     * Using "async()->getReturn()" to get async function returned result
     *
     * @param \Fiber        $child_fiber
     * @param callable|null $callable
     *
     * @return \Fiber
     */
    public function async(\Fiber $child_fiber, callable $callable = null): \Fiber
    {
        $fiber = new \Fiber(function () use ($child_fiber, $callable): mixed
        {
            $result = null;

            while (!$child_fiber->isTerminated()) {
                if ($child_fiber->isSuspended()) {
                    $child_fiber->resume();
                }

                if ($child_fiber->isTerminated()) {
                    $result = $child_fiber->getReturn();

                    if (is_callable($callable)) {
                        $args = is_array($result) && !empty($result) && !array_is_list($result)
                            ? parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $result)
                            : [$result];

                        $result = call_user_func_array($callable, $args);
                    }

                    break;
                }

                \Fiber::suspend();
            }

            unset($child_fiber, $callable, $args);
            return $result;
        });

        $this->child[] = $fiber;

        unset($child_fiber, $callable);
        return $fiber;
    }

    /**
     * Resume main fiber process
     *
     * @return void
     * @throws \Throwable
     */
    public function go(): void
    {
        $this->fiber->resume();
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