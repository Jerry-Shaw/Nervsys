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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;
use Nervsys\Core\Reflect;

class FiberMan extends Factory
{
    private \Fiber $fiber;
    private array  $fibers = [];

    /**
     * @throws \Throwable
     */
    public function __construct()
    {
        $this->fiber = new \Fiber([$this, 'run']);
        $this->fiber->start();
    }

    /**
     * @param callable $callable
     * @param array    $args
     *
     * @return \Fiber
     * @throws \Throwable
     */
    public function async(callable $callable, array $args = []): \Fiber
    {
        $fiber = new \Fiber(function () use ($callable, $args): mixed
        {
            $child_fiber    = new \Fiber($callable);
            $this->fibers[] = \Fiber::getCurrent();

            \Fiber::suspend();

            $child_fiber->start(...parent::buildArgs(Reflect::getCallable($callable)->getParameters(), $args));

            if ($child_fiber->isSuspended()) {
                \Fiber::suspend();
                $child_fiber->resume();
            }

            unset($callable, $args);
            return $child_fiber->getReturn();
        });

        $fiber->start();

        unset($callable, $args);
        return $fiber;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function await(): void
    {
        if ($this->fiber->isSuspended()) {
            $this->fiber->resume();
        }
    }

    /**
     * @param \Fiber        $fiber
     * @param callable|null $callable
     * @param bool          $await
     *
     * @return mixed
     * @throws \Throwable
     */
    public function then(\Fiber $fiber, callable $callable = null, bool $await = true): mixed
    {
        if ($await) {
            $this->await();
        } elseif ($fiber->isSuspended()) {
            $fiber->resume();
        }

        $fiber_return = !is_callable($callable)
            ? $fiber->getReturn()
            : $this->then($this->async($callable, (array)$fiber->getReturn()), null, $await);

        unset($fiber, $callable, $await);
        return $fiber_return;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    private function run(): void
    {
        /** @var \Fiber $fiber */
        foreach ($this->fibers as $key => $fiber) {
            if ($fiber->isRunning()) {
                continue;
            }

            if (!$fiber->isStarted()) {
                $fiber->start();
            }

            if ($fiber->isSuspended()) {
                $fiber->resume();
            }

            if ($fiber->isTerminated()) {
                unset($this->fibers[$key]);
            }
        }

        //Suspend main Fiber
        \Fiber::suspend();

        //Start another loop
        $this->run();
    }
}