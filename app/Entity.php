<?php

namespace App;

use Illuminate\Support\Arr;

abstract class Entity
{
    public function __construct(private $data)
    {
    }

    protected function get(string $key)
    {
        return Arr::get($this->data, $key);
    }

    public function __debugInfo(): array
    {
        return $this->data;
    }
}
