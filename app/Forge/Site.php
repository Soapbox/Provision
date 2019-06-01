<?php

namespace App\Forge;

use App\Entity;

class Site extends Entity
{
    public function getId(): string
    {
        return $this->get('id');
    }

    public function getName(): string
    {
        return $this->get('name');
    }

    public function isDefault(): bool
    {
        return $this->getName() == 'default';
    }

    public function isInstalled(): bool
    {
        return $this->get('status') == 'installed';
    }

    public function isWildcard(): bool
    {
        return $this->get('wildcards');
    }
}
