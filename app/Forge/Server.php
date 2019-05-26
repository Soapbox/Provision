<?php

namespace App\Forge;

use App\Entity;

class Server extends Entity
{
    public function getId(): string
    {
        return $this->get('id');
    }

    public function getName(): string
    {
        return $this->get('name');
    }
}
