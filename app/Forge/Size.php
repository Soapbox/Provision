<?php

namespace App\Forge;

class Size
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

    public function getSize(): string
    {
        return $this->data['size'];
    }

    public function isFor(string $size): bool
    {
        return $this->getSize() == $size;
    }
}
