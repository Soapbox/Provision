<?php

namespace App\EC2;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EC2
{
    public static function test(): void
    {
        $client = new \Aws\Ec2\Ec2Client([
            'region' => 'us-west-1',
            'version' => '2016-11-15',
        ]);

        collect($client->describeInstances()['Reservations'])->map(function ($item) {
            return $item['Instances'];
        })->flatten(1)->map(function ($i) {
            return $i['KeyName'];
        })->dump();
    }

    public static function getInstances(): Collection
    {
        return Cache::remember('ec2.instances', Carbon::now()->addDay(), function () {
            $client = new \Aws\Ec2\Ec2Client([
                'region' => 'us-west-1',
                'version' => '2016-11-15',
            ]);

            return collect($client->describeInstances()['Reservations'])->map(function ($item) {
                return $item['Instances'];
            })->flatten(1)->mapInto(Instance::class);
        });
    }

    public static function getInstance(string $server): Instance
    {
        return self::getInstances()->first(function ($instance) use ($server) {
            return $instance->getName() == $server;
        });
    }

    public static function disableUnlimited(array $servers): void
    {
        $args = [
            'InstanceCreditSpecification' => array_map(function ($server) {
                return [
                    'CpuCredits' => 'standard',
                    'InstanceId' => self::getInstance($server)->getId(),
                ];
            }, $servers),
        ];
        dd($args);
        $client->modifyInstanceCreditSpecification($args);
    }

    public static function addTags(array $servers, array $tags): void
    {
        $args = [
            'Resources' => array_map(function ($server) {
                return self::getInstance($server)->getId();
            }, $servers),
            'Tags' => collect($tags)->map(function ($value, $key) {
                return [
                    'Key' => $key,
                    'Value' => $value,
                ];
            })->values(),
        ];
        dd($args);
    }
}
