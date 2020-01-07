<?php

namespace App\Forge;

use App\Nginx;
use App\Recipe;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use App\WorkerConfiguration;
use Illuminate\Support\Collection;
use App\Exceptions\ResourceNotFound;
use Illuminate\Support\Facades\Cache;
use JSHayes\FakeRequests\ClientFactory;

class Forge
{
    const DATABASE_NONE = '';

    const PHP_72 = 'php72';

    private $client;

    public function __construct(ClientFactory $factory)
    {
        $this->client = $factory->make([
            'base_uri' => 'https://forge.laravel.com/api/v1/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.forge.token'),
            ],
        ]);
    }

    public function getRegions(): Collection
    {
        return Cache::remember('forge.regions', Carbon::now()->addDay(), function () {
            $response = json_decode($this->client->get('regions')->getBody(), true);
            return collect(Arr::get($response, 'regions.aws'))->mapInto(Region::class);
        });
    }

    public function getRegion(string $region): ?Region
    {
        return $this->getRegions()->first->isFor($region);
    }

    public function getServers(): Collection
    {
        return Cache::remember('forge.servers', Carbon::now()->addDay(), function () {
            $response = json_decode($this->client->get('servers')->getBody(), true);
            return collect(Arr::get($response, 'servers'))->mapInto(Server::class);
        });
    }

    public function getServersByPattern(string $pattern): Collection
    {
        $pattern = '/' . trim($pattern, '/') . '/';
        return $this->getServers()->filter(function (Server $server) use ($pattern) {
            return preg_match($pattern, $server->getName());
        });
    }

    public function getServer(string $serverName): Server
    {
        $server = self::getServers()->first(function (Server $server) use ($serverName) {
            return $server->getName() == $serverName;
        });

        if (is_null($server)) {
            throw new ResourceNotFound();
        }

        return $server;
    }

    public function createServer(array $params): Server
    {
        $response = $this->client->post('servers', ['json' => $params]);
        Cache::forget('forge.servers');
        Cache::forget('ec2.instances');

        $this->saveServerMeta($response);

        return new Server(Arr::get(json_decode($response->getBody(), true), 'server'));
    }

    private function saveServerMeta($response)
    {
        $filename = 'Provision-' . Carbon::now()->format('Y-m-d-H-i-s');

        file_put_contents(storage_path() . '/' . $filename, $response->getBody());
    }

    public function updateServer(Server $server, array $params): Server
    {
        $response = $this->client->put("servers/{$server->getId()}", ['json' => $params]);
        Cache::forget('forge.servers');
        return new Server(Arr::get(json_decode($response->getBody(), true), 'server'));
    }

    public function getSites(Server $server): Collection
    {
        return Cache::remember("forge.server.{$server->getId()}.sites", Carbon::now()->addDay(), function () use ($server) {
            $response = json_decode($this->client->get("servers/{$server->getId()}/sites")->getBody(), true);
            return collect(Arr::get($response, 'sites'))->map(function ($site) use ($server) {
                return new Site($site, $server);
            });
        });
    }

    public function getSite(Server $server, string $name): ?Site
    {
        return $this->getSites($server)->first(function ($site) use ($name) {
            return $site->getName() == $name;
        });
    }

    public function createSite(Server $server, array $params): Site
    {
        $response = $this->client->post("servers/{$server->getId()}/sites", ['json' => $params]);
        Cache::forget("forge.server.{$server->getId()}.sites");
        return new Site(json_decode($response->getBody(), true)['site'], $server);
    }

    public function deleteSite(Site $site): void
    {
        $server = $site->getServer();
        $this->client->delete("servers/{$server->getId()}/sites/{$site->getId()}");
        Cache::forget("servers.{$server->getId()}.sites");
    }

    public function updateNginxConfig(Site $site, Nginx $nginx): void
    {
        $server = $site->getServer();
        $this->client->put("servers/{$server->getId()}/sites/{$site->getId()}/nginx", ['json' => [
            'content' => (string) $nginx,
        ]]);
    }

    public function runRecipe(Server $server, Recipe $recipe): void
    {
        $response = $this->client->post('recipes', [
            'json' => [
                'name' => $recipe->getName(),
                'user' => $recipe->getUser(),
                'script' => $recipe->getScript(),
            ],
        ]);

        $recipeId = Arr::get(json_decode($response->getBody(), true), 'recipe.id');

        $this->client->post("recipes/$recipeId/run", [
            'json' => [
                'servers' => [$server->getId()],
            ],
        ]);
    }

    public function getWorkers(Site $site): Collection
    {
        $server = $site->getServer();
        return Cache::remember(
            "forge.server.{$server->getId()}.sites.{$site->getId()}.workers",
            Carbon::now()->addDay(),
            function () use ($server, $site) {
                $response = $this->client->get("servers/{$server->getId()}/sites/{$site->getId()}/workers");
                $response = json_decode($response->getBody(), true);
                return collect(Arr::get($response, 'workers'))->map(function ($worker) use ($site) {
                    return new Worker($worker, $site);
                });
            }
        );
    }

    public function createWorker(Site $site, WorkerConfiguration $worker): void
    {
        $server = $site->getServer();
        $this->client->post("servers/{$server->getId()}/sites/{$site->getId()}/workers", [
            'json' => [
                'queue' => $worker->getQueue(),
                'connection' => $worker->getConnection(),
                'timeout' => $worker->getTimeout(),
                'sleep' => $worker->getSleep(),
                'tries' => $worker->getTries(),
                'delay' => $worker->getDelay(),
                'processes' => $worker->getProcesses(),
                'daemon' => $worker->isDaemon(),
            ],
        ]);
        Cache::forget("forge.server.{$server->getId()}.sites.{$site->getId()}.workers");
    }

    public function deleteWorker(Worker $worker): void
    {
        $site = $worker->getSite();
        $server = $site->getServer();
        $this->client->delete("servers/{$server->getId()}/sites/{$site->getId()}/workers/{$worker->getId()}");
        Cache::forget("forge.server.{$server->getId()}.sites.{$site->getId()}.workers");
    }
}
