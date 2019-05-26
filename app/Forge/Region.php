<?php

namespace App\Forge;

use App\Entity;
use Illuminate\Support\Collection;

class Region extends Entity
{
    public function getId(): string
    {
        return $this->get('id');
    }

    public function isFor(string $region): bool
    {
        return $this->getId() == $region;
    }

    public function getSizes(): Collection
    {
        return collect($this->get('sizes'))->mapInto(Size::class);
    }

    public function getSize(string $size): Size
    {
        return $this->getSizes()->first->isFor($size);
    }
}
