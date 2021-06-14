<?php

namespace App\EC2;

use Carbon\Carbon;
use Aws\Ec2\Ec2Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use JSHayes\FakeRequests\ClientFactory;

class EC2
{
    private $client;

    public function __construct(ClientFactory $factory)
    {
        $this->client = new Ec2Client([
            'region' => 'us-west-1',
            'version' => '2016-11-15',
            'http_handler' => new GuzzleHandler($factory->make()),
        ]);
    }

    public function getInstances(): Collection
    {
        return Cache::remember(
            'ec2.instances',
            Carbon::now()->addDay(),
            fn () => collect($this->client->describeInstances()['Reservations'])->map(
                fn ($item) => $item['Instances']
            )->flatten(1)->mapInto(Instance::class)
        );
    }

    public function getInstance(string $server): Instance
    {
        return $this->getInstances()->first(fn ($instance) => $instance->getName() == $server);
    }

    public function disableUnlimited(array $servers): void
    {
        $args = [
            'InstanceCreditSpecifications' => array_map(fn ($server) =>
                [
                    'CpuCredits' => 'standard',
                    'InstanceId' => $this->getInstance($server)->getId(),
                ], $servers),
        ];

        $this->client->modifyInstanceCreditSpecification($args);
    }

    public function addTags(array $servers, array $tags): void
    {
        $args = [
            'Resources' => array_map(fn ($server) => $this->getInstance($server)->getId(), $servers),
            'Tags' => collect($tags)->map(
                fn ($value, $key) => [ 'Key' => $key, 'Value' => $value, ]
            )->values()->toArray(),
        ];

        $this->client->createTags($args);
    }
}
