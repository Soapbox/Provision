<?php

namespace App\Queues\Balancing;

use App\Forge\Site;
use App\EC2\Instance;
use Illuminate\Support\Collection;

class Balancer
{
    public function __construct(Collection $balancingRules)
    {
        $this->nodes = new Collection();
        $this->list = new LinkedList();
        $this->rules = $balancingRules->keyBy->getServerSize();
    }

    public function addSite(Site $site, Instance $instance): void
    {
        $node = new Node($site, $this->rules->get($instance->getInstanceType()));
        $this->list->append($node);
        $this->nodes->put($site->getId(), $node);
    }

    private function add(string $queue): void
    {
        $node = $this->list->pop();
        $node->addQueue($queue);

        if (! $node->isFull()) {
            $this->list->append($node);
        }
    }

    public function addQueue(string $queue, int $processes): void
    {
        for ($i = 0; $i < $processes; $i++) {
            $this->add($queue);
        }
    }

    public function getBalancedQueues(): Collection
    {
        return $this->nodes->map(function (Node $node) {
            return $node->getQueues();
        });
    }
}
