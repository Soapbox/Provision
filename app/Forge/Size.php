<?php

namespace App\Forge;

class Size extends Entity
{
    public function getId(): string
    {
        return $this->get('id');
    }

    public function getSize(): string
    {
        return $this->get('size');
    }

    public function isFor(string $size): bool
    {
        return $this->getSize() == $size;
    }
}
