<?php

namespace App\Validators;

use Closure;
use App\EC2\EC2;
use App\Forge\Forge;
use App\Forge\Server;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class QueueConfigValidator
{
    public function __construct(private Forge $forge, private EC2 $ec2)
    {
    }

    private function validateBalancing(Collection $servers): Closure
    {
        return function ($attribute, $value, $fail) use ($servers) {
            $instanceTypes = $servers->map(fn (Server $server) => $this->ec2->getInstance($server->getName())->getInstanceType());

            $balancedTypes = array_map(fn ($balancing) => $balancing['server-size'], $value);

            foreach ($instanceTypes as $instanceType) {
                if (! in_array($instanceType, $balancedTypes)) {
                    $fail("There is no balancing defined for the instance type of $instanceType");
                }
            }
        };
    }

    public function validate(array $config): void
    {
        Validator::make($config, [
            'sites' => 'required|array',
            'sites.server-name' => 'required|string',
            'sites.site-name' => 'required|string',
        ])->validate();

        $servers = $this->forge->getServersByPattern(Arr::get($config, 'sites.server-name'));
        $data = $servers->map(
            fn (Server $server) => [
                'server' => $server->getName(),
                'site' => optional($this->forge->getSite($server, Arr::get($config, 'sites.site-name')))->getName(),
            ]
        )->toArray();
        Validator::make(['servers' => $data], [
            'servers' => 'array|min:1',
            'servers.*.server' => 'required|string',
            'servers.*.site' => 'required|string',
        ])->validate();

        $rules = [
            'balancing' => ['required', 'array', $this->validateBalancing($servers)],
            'balancing.*' => 'required|array',
            'balancing.*.server-size' => 'required|distinct',
            'balancing.*.max-processes' => 'required|int|min:1',
            'queues' => 'required|array',
            'queues.*' => 'required|array',
            'queues.*.queue' => 'required|string|distinct',
            'queues.*.connection' => 'required|string',
            'queues.*.timeout' => 'required|int|min:1',
            'queues.*.sleep' => 'required|int|min:0',
            'queues.*.failed-job-delay' => 'required|int|min:0',
            'queues.*.processes' => 'required|int|min:1',
            'queues.*.maximum-tries' => 'required|int|min:0',
            'queues.*.daemon' => 'required|boolean',
        ];

        Validator::make($config, $rules)->validate();
    }
}
