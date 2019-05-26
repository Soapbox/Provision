<?php

namespace App\Forge;

use Illuminate\Support\Collection;

class Region
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    public function isFor(string $region): bool
    {
        return $this->getId() == $region;
    }

    public function getSizes(): Collection
    {
        return collect($this->data['sizes'])->mapInto(Size::class);
    }

    public function getSize(string $size): Size
    {
        return $this->getSizes()->first->isFor($size);
    }
}
