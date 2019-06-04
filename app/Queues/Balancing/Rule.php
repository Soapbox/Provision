<?php

namespace App\Queues\Balancing;

class Rule
{
    private $rules;

    public function __construct(array $rules)
    {
        $this->rules = $rules;
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
