<?php

namespace App\Forge;

use App\Entity;

class Site extends Entity
{
    private $server;

    public function __construct(array $data, Server $server)
    {
        parent::__construct($data);
        $this->server = $server;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

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
