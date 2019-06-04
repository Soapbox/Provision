<?php

namespace App;

use App\Forge\Site;
use Illuminate\Support\Collection;

class WorkerDiff
{
    private $existing;

    private $configured;

    public function __construct(Site $site, Collection $existing, Collection $configured)
    {
        $this->site = $site;

        $this->existing = $existing->keyBy(function ($worker) {
            return $this->makeKey($worker);
        });
        $this->configured = $configured->keyBy(function ($worker) {
            return $this->makeKey($worker);
        });
    }

    private function makeKey(WorkerInterface $worker): string
    {
        return sprintf(
            '%s:%s:%s:%s:%s:%s:%s:%s',
            $worker->getQueue(),
            $worker->getConnection(),
            $worker->getProcesses(),
            $worker->getTimeout(),
            $worker->getDelay(),
            $worker->getSleep(),
            $worker->getTries(),
            $worker->isDaemon() ? 'daemon' : 'not-daemon'
        );
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function workersToDelete(): Collection
    {
        return $this->existing->diffKeys($this->configured);
    }

    public function workersToCreate(): Collection
    {
        return $this->configured->diffKeys($this->existing);
    }
}
