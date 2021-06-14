<?php

namespace App\Queues\Balancing;

class Rule
{
    public function __construct(private array $rules)
    {
    }

    public function getServerSize(): string
    {
        return $this->rules['server-size'];
    }

    public function getMaximumProcessCount(): int
    {
        return $this->rules['max-processes'];
    }
}
