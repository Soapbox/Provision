<?php

namespace App\Queues\Balancing;

use App\Forge\Site;

class Node
{
    public $next;
    public $prev;
    private $queues = [];

    public function __construct(Site $site, Rule $rule)
    {
        $this->site = $site;
        $this->rule = $rule;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getQueues(): array
    {
        return $this->queues;
    }

    public function addQueue(string $queue): void
    {
        if (! array_key_exists($queue, $this->queues)) {
            $this->queues[$queue] = 0;
        }

        $this->queues[$queue]++;
    }

    public function isFull(): bool
    {
        return array_sum($this->queues) >= $this->rule->getMaximumProcessCount();
    }
}
