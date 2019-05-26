<?php

namespace App;

use Illuminate\Support\Arr;

abstract class Entity
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
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
