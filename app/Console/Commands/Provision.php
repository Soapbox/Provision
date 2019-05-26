<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Provision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'provision';

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
        $services = array_keys(config('sites'));
        $service = $this->choice('Which service would you like to privision sites for?', $services);

        $serverTypes = array_keys(config("sites.$service"));
        $type = $this->choice('Which server type would you like to provision sites for?', $serverTypes);

        $config = config("sites.$service.$type");
        dd($config);

        AWS::disableUnlimited($config['servers']);
        AWS::addTags($config['servers'], $config['tags']);

        foreach ($config['servers'] as $server) {
            $server = Forge::getServer($server);
            $this->info("Configuring {$server->getName()}");

            $sites = Forge::getSites($server);
            $sites->filter->isDefault()->each(function ($site) use ($server) {
                Forge::deleteSite($server, $site);
            });
            $sites = $sites->reject->isDefault()->keyBy->getName();

            foreach ($config['sites'] as $siteConfig) {
                if ($sites->has($siteConfig['domain'])) {
                    $site = $sites->get($siteConfig['domain']);

                    if ($diff = $site->diff($siteConfig)) {
                        $this->info(sprintf(
                            "Looks like a site for %s already exists, but it is configured differently.",
                            $siteConfig['domain']
                        ));
                        $this->line($diff);
                        if ($this->confirm('Would you like to update this site?')) {
                            Forge::updateSite($site, $siteConfig);
                        }
                    }

                    $existing = true;
                } else {
                    $site = Forge::createSite($server, $siteConfig);
                    $existing = false;
                }

                $nginxConfig = Forge::getNginxConfig($site);
                $newConfig = Nginx::loadFrom($config['nginx']);

                if ($diff = $nginxConfig->diff($newConfig)) {
                    if ($existing) {
                        $this->info("Looks like the nginx configuration is different.");
                        $this->line($diff);
                        $this->ask('Would you like to update this configuration?');

                        Forge::updateNginxConfiguration($site, $newConfig);
                    } else {
                        Forge::updateNginxConfiguration($site, $newConfig);
                    }
                }

                $this->info("Done configuring {$server->getName()}");
            }

            $this->info("Running recipies");
            (new Recipe($config))->execute();

            // Configure the network
            // Install ssh keys
            // Install LogDNA & DataDog
            // Delete default site
            // Install configured sites
            // For each installed site, set up nginx
            // For each site, configure LogDNA
        }
    }
}
