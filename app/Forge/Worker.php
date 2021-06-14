<?php

namespace App\Forge;

use App\Entity;
use App\WorkerInterface;

class Worker extends Entity implements WorkerInterface
{
    public function __construct(array $data, private Site $site)
    {
        parent::__construct($data);
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getId(): int
    {
        return $this->get('id');
    }

    public function getConnection(): string
    {
        return $this->get('connection');
    }

    public function getQueue(): string
    {
        return $this->get('queue');
    }

    public function getTimeout(): int
    {
        return $this->get('timeout');
    }

    public function getDelay(): int
    {
        return $this->get('delay');
    }

    public function getSleep(): int
    {
        return $this->get('sleep');
    }

    public function getTries(): int
    {
        return $this->get('tries');
    }

    public function getProcesses(): int
    {
        return $this->get('processes');
    }

    public function isDaemon(): bool
    {
        return $this->get('daemon');
    }
}
