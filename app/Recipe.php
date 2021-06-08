<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class Recipe
{
    public function __construct(Collection $scripts)
    {
        $this->scripts = $scripts;
    }

    public function getName(): string
    {
        return 'Provision - '.Carbon::now();
    }

    public function getUser(): string
    {
        return 'root';
    }

    public function getScript(): string
    {
        return $this->scripts->map->getCode()->implode("\n");
    }
}
