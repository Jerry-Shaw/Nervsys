<?php

/**
 * Fiber Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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
    private array $stacks = [];

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
            $this->fibers[] = [$await_fiber, $callback];
        }

        unset($callable, $arguments, $callback, $await_fiber);
    }

    /**
     * Submit all async fibers in queue
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
        }
    }

    /**
     * Create a new task stack
     *
     * @param string $stack_id
     *
     * @return void
     */
    public function createStack(string $stack_id): void
    {
        if (!isset($this->stacks[$stack_id])) {
            $this->stacks[$stack_id] = [
                'tasks'   => [],
                'results' => []
            ];
        }
    }

    /**
     * Add a task to stack
     *
     * @param string   $stack_id
     * @param callable $task
     * @param array    $args
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function addTask(string $stack_id, callable $task, array $args = []): void
    {
        if (!isset($this->stacks[$stack_id])) {
            $this->createStack($stack_id);
        }

        $fiber = $this->await($task, $args);

        // Handle already terminated fiber
        if ($fiber->isTerminated()) {
            $this->stacks[$stack_id]['results'][] = $fiber->getReturn();
            unset($fiber);
            return;
        }

        $this->stacks[$stack_id]['tasks'][] = $fiber;
    }

    /**
     * Run next task in stack
     *
     * @param string $stack_id
     *
     * @return bool
     * @throws \Throwable
     */
    public function runNext(string $stack_id): bool
    {
        if (!isset($this->stacks[$stack_id])) {
            return false;
        }

        $stack = &$this->stacks[$stack_id];

        if (empty($stack['tasks'])) {
            return false;
        }

        // Get and remove first task
        $fiber = array_shift($stack['tasks']);

        try {
            if ($fiber->isSuspended()) {
                $fiber->resume();
            }

            if ($fiber->isTerminated()) {
                $stack['results'][] = $fiber->getReturn();
            }
        } catch (\Throwable $e) {
            $stack['results'][] = [
                'error'   => true,
                'message' => $e->getMessage(),
                'code'    => $e->getCode()
            ];
        }

        unset($fiber);
        return true;
    }

    /**
     * Run entire stack until completion
     *
     * @param string $stack_id
     *
     * @return array
     * @throws \Throwable
     */
    public function runStack(string $stack_id): array
    {
        if (!isset($this->stacks[$stack_id])) {
            return [];
        }

        while ($this->runNext($stack_id)) {
            // Continue execution
        }

        $results = $this->stacks[$stack_id]['results'];
        unset($this->stacks[$stack_id]);

        return $results;
    }

    /**
     * Check if stack exists
     *
     * @param string $stack_id
     *
     * @return bool
     */
    public function hasStack(string $stack_id): bool
    {
        return isset($this->stacks[$stack_id]);
    }

    /**
     * Get stack status
     *
     * @param string $stack_id
     *
     * @return array|null
     */
    public function getStackStatus(string $stack_id): array|null
    {
        if (!isset($this->stacks[$stack_id])) {
            return null;
        }

        $stack = $this->stacks[$stack_id];

        return [
            'pending' => count($stack['tasks']),
            'results' => count($stack['results'])
        ];
    }

    /**
     * Clear stack from memory
     *
     * @param string $stack_id
     *
     * @return void
     */
    public function clearStack(string $stack_id): void
    {
        if (isset($this->stacks[$stack_id])) {
            $stack = &$this->stacks[$stack_id];

            // Clean up fiber references to prevent memory leaks
            foreach ($stack['tasks'] as $task) {
                unset($task);
            }

            unset($this->stacks[$stack_id]);
        }
    }

    /**
     * Handle fiber completion and callback
     *
     * @param \Fiber   $fiber
     * @param callable $callback
     *
     * @return void
     * @throws \ReflectionException
     */
    private function fiberDone(\Fiber $fiber, callable $callback): void
    {
        $result = $fiber->getReturn();

        if (is_array($result) && !array_is_list($result)) {
            $result = parent::buildArgs(Reflect::getCallable($callback)->getParameters(), $result);
        }

        if (is_array($result)) {
            call_user_func($callback, ...$result);
        } else {
            call_user_func($callback, $result);
        }

        unset($fiber, $callback, $result);
    }
}