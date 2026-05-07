<?php

/**
 * Your Module Name
 *
 * A brief description of what this module does
 */

namespace modules\demo_module;

use Nervsys\Core\Factory;

class go extends Factory
{
    /**
     * Example method - replace with your own business logic
     *
     * @param string $param
     * @return string
     */
    public function hello(string $param = 'World'): string
    {
        return 'Hello, ' . $param . '!';
    }

    /**
     * Another example method - modify as needed
     */
    public function process(array $data): array
    {
        // Your business logic here
        return $data;
    }
}