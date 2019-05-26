<?php

namespace App\Forge;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Forge
{
    const DATABASE_NONE = '';

    const PHP_72 = 'php72';

    private static function getClient(): Client
    {
        return new Client([
            'base_uri' => 'https://forge.laravel.com/api/v1/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.forge.token'),
            ],
        ]);
    }

    public static function getRegions(): Collection
    {
        return Cache::remember('forge.regions', Carbon::now()->addDay(), function () {
            $response = json_decode(self::getClient()->get('regions')->getBody(), true);
            return collect(Arr::get($response, 'regions.aws'))->mapInto(Region::class);
        });
    }

    public static function getServers(): Collection
    {
        return Cache::remember('forge.servers', Carbon::now()->addDay(), function () {
            $response = json_decode(self::getClient()->get('servers')->getBody(), true);
            return collect(Arr::get($response, 'servers'))->mapInto(Server::class);
        });
    }

    public static function getServer(string $serverName): Server
    {
        return self::getServers()->first(function (Server $server) use ($serverName) {
            return $server->getName() == $serverName;
        });
    }

    public static function getSites(Server $server): Collection
    {
        $serverId = $server->getId();
        $response = json_decode(self::getClient()->get("servers/$serverId/sites")->getBody(), true);
        return collect(Arr::get($response, 'sites'))->mapInto(Site::class);
    }
}
