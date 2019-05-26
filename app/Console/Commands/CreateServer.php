<?php

namespace App\Console\Commands;

use App\EC2\EC2;
use App\Forge\Forge;
use App\SiteConfigUpdater;
use Illuminate\Console\Command;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $services = array_keys(config('servers'));
        $service = $this->choice('Which service would you like to create a server for?', $services);

        $serverTypes = array_keys(config("servers.$service"));
        $type = $this->choice('Which server type would you like to create?', $serverTypes);

        $config = config("servers.$service.$type");

        $region = Forge::getRegions()->first->isFor($config['region']);
        $size = $region->getSize($config['size']);

        $namePattern = str_replace('{number}', '\d+', $config['name']);
        $instance = EC2::getInstances()->filter(function ($instance) use ($namePattern) {
            return preg_match("/$namePattern/", $instance->getName());
        })->sortBy->getName()->last();

        $sections = explode('\d+', $namePattern);
        $number = str_replace($sections, '', $instance->getName());
        $name = str_replace('\d+', sprintf('%03d', $number + 1), $namePattern);

        $params = [
            'provider' => 'aws',
            'credentials' => 21293,
            'name' => $name,
            'size' => $size->getId(),
            'region' => $config['region'],
            'php_version' => $config['php_version'],
            'database_type' => $config['database_type'],
            'aws_vpc_id' => 'vpc-341f2751',
            'aws_subnet_id' => 'subnet-bcbfb7e5',
        ];
        $this->table(array_keys($params), [$params]);

        if (!$this->confirm('Are you sure you want to create this server?')) {
            return 1;
        }
        (new SiteConfigUpdater())->addServerToConfig($service, $type, $name);
        dd($params);

        $this->line('Provisioning a server. This will take a few minutes.');
        $server = Forge::createServer($params);

        while (!$server->isReady()) {
            sleep(60);
            Cache::forge('forge.servers');
            $server = Forge::getServer($server->getName());
        }
        $this->line('Provisioning complete.');
    }
}
