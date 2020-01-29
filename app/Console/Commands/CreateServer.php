<?php

namespace App\Console\Commands;

use App\Nginx;
use App\Recipe;
use App\Script;
use App\Waiter;
use App\EC2\EC2;
use App\Forge\Site;
use App\Forge\Forge;
use App\Forge\Server;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Validators\ServerConfigValidator;
use Illuminate\Validation\ValidationException;

class CreateServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * @var \App\Forge\Forge
     */
    private $forge;

    /**
     * @var \App\EC2\EC2
     */
    private $ec2;

    private $waiter;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Forge $forge, EC2 $ec2, Waiter $waiter)
    {
        parent::__construct();

        $this->forge = $forge;
        $this->ec2 = $ec2;
        $this->waiter = $waiter;
    }

    private function getServerSize(array $config): string
    {
        $region = $this->forge->getRegion(Arr::get($config, 'config.region'));
        return $region->getSize(Arr::get($config, 'config.size'))->getId();
    }

    private function getNextServerName(array $config): string
    {
        $namePattern = Arr::get($config, 'config.name') . '-\d+';
        $instance = $this->ec2->getInstances()->filter(function ($instance) use ($namePattern) {
            return preg_match("/$namePattern/", $instance->getName());
        })->sortBy->getName()->last();

        if (is_null($instance)) {
            return str_replace('\d+', sprintf('%03d', 1), $namePattern);
        }

        $sections = explode('\d+', $namePattern);
        $number = str_replace($sections, '', $instance->getName());
        return str_replace('\d+', sprintf('%03d', $number + 1), $namePattern);
    }

    private function getNetworkedServers(array $config): array
    {
        return collect(Arr::get($config, 'network'))->map(function ($server) {
            return $this->forge->getServer($server)->getId();
        })->values()->toArray();
    }

    private function provisionServer(array $config): Server
    {
        $params = [
            'name' => $this->getNextServerName($config),
            'size' => $this->getServerSize($config),
            'region' => Arr::get($config, 'config.region'),
            'php_version' => Arr::get($config, 'config.php-version'),
            'database_type' => Arr::get($config, 'config.database-type'),
            'aws_vpc_id' => 'vpc-341f2751',
            'aws_subnet_id' => 'subnet-bcbfb7e5',
            'network' => implode(',', $this->getNetworkedServers($config)),
        ];

        $this->table(array_keys($params), [$params]);

        $params = array_merge($params, [
            'provider' => 'aws',
            'credential_id' => 21293,
            'network' => $this->getNetworkedServers($config),
        ]);

        if (!$this->confirm('Are you sure you want to create this server?')) {
            exit(1);
        }

        $this->line('Provisioning a server. This will take a few minutes.');
        $server = $this->forge->createServer($params);
        $this->waiter->waitFor(function () use (&$server) {
            Cache::forget('forge.servers');
            $server = $this->forge->getServer($server->getName());
            return !$server->isReady();
        }, 60);
        $this->line("Provisioning complete.\n");

        $this->forge->updateServer($server, [
            'max_upload_size' => Arr::get($config, 'config.max-upload-size'),
            'network' => $this->getNetworkedServers($config),
        ]);

        return $server;
    }

    private function provisionSite(Server $server, array $config): Site
    {
        $this->line('Installing site: ' . Arr::get($config, 'config.domain'));

        $params = [
            'domain' => Arr::get($config, 'config.domain'),
            'project_type' => Arr::get($config, 'config.type'),
            'aliases' => Arr::get($config, 'config.aliases'),
            'directory' => Arr::get($config, 'config.directory'),
            'wildcards' => Arr::get($config, 'config.wildcards'),
        ];
        $site = $this->forge->createSite($server, $params);
        $this->waiter->waitFor(function () use ($server, &$site) {
            Cache::forget("forge.server.{$server->getId()}.sites");
            $site = $this->forge->getSite($server, $site->getName());
            return !$site->isInstalled();
        }, 5);

        $nginx = Arr::get($config, 'nginx');
        if (!empty($nginx)) {
            $this->forge->updateNginxConfig($site, new Nginx($nginx, $site));
        }

        return $site;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ServerConfigValidator $validator)
    {
        $services = array_keys(config('servers'));
        $service = $this->choice('Which service would you like to create a server for?', $services);

        $serverTypes = array_keys(config("servers.$service"));
        $type = $this->choice('Which server type would you like to create?', $serverTypes);

        $config = config("servers.$service.$type");

        try {
            $validator->validate($config);
        } catch (ValidationException $e) {
            throw new \Exception(json_encode($e->errors(), JSON_PRETTY_PRINT));
            $this->error('The config file is invalid.');
            $this->line(json_encode($e->errors(), JSON_PRETTY_PRINT));
            return 1;
        }

        $server = $this->provisionServer($config);

        $this->line('Disabling Unlimited');
        $this->ec2->disableUnlimited([$server->getName()]);

        $this->line('Adding server tags');
        $this->ec2->addTags([$server->getName()], $config['tags']);

        $this->line('Provisioning Sites');
        $this->forge->deleteSite($this->forge->getSite($server, 'default'));
        foreach (Arr::get($config, 'sites') as $siteConfig) {
            $this->provisionSite($server, $siteConfig);
        }

        $this->line('Running post provision script');
        $scripts = Arr::get($config, 'scripts');
        foreach (Arr::get($config, 'sites') as $siteConfig) {
            $scripts = array_merge($scripts, Arr::get($siteConfig, 'scripts'));
        }

        $scripts = collect($scripts)->map(function ($script) {
            return new Script($script['script'], $script['arguments']);
        });
        $recipe = new Recipe($scripts);
        $this->forge->runRecipe($server, $recipe);

        $this->line('Server provisioning complete.');
        $this->line('See https://github.com/Soapbox/Provision/wiki/Additional-Steps for the remaining manual steps');
    }
}
