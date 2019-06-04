<?php

namespace App;

class WorkerConfiguration extends Entity implements WorkerInterface
{
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
        return $this->get('failed-job-delay');
    }

    public function getSleep(): int
    {
        return $this->get('sleep');
    }

    public function getTries(): int
    {
        return $this->get('maximum-tries');
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
