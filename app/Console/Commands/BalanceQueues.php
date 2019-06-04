<?php

namespace App\Console\Commands;

use App\EC2\EC2;
use App\SQS\SQS;
use App\WorkerDiff;
use App\Forge\Forge;
use Illuminate\Support\Arr;
use App\WorkerConfiguration;
use App\Queues\Balancing\Rule;
use Illuminate\Console\Command;
use App\Queues\Balancing\Balancer;

class BalanceQueues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queues:balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebalance queues across servers';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Forge $forge, SQS $sqs, EC2 $ec2)
    {
        $services = array_keys(config('queues'));
        $service = $this->choice('Which service would you like to balance queues for?', $services);

        $config = config("queues.$service");

        $queues = collect(Arr::get($config, 'queues'))->keyBy('queue');
        $queueUrls = $sqs->listQueues()->keyBy(function ($url) {
            return substr($url, strrpos($url, '/') + 1);
        });
        $timeouts = $queueUrls->intersectByKeys($queues)->map(function ($url) use ($sqs) {
            return $sqs->getVisibilityTimeout($url);
        });

        foreach ($queues as $queue) {
            $visibilityTimeout = $timeouts->get($queue['queue']);
            if ($queue['timeout'] + 30 > $visibilityTimeout) {
                $this->error("Queue {$queue['queue']} has a timeout too small for the configured visibility timeout.");
                $this->line("Queue Timeout: {$queue['timeout']}");
                $this->line("Visiblity Timeout: {$visibilityTimeout}");

                $newVisibilityTimeout = $queue['timeout'] + 30;
                if ($this->confirm("Would you like to update the visibility timeout to {$newVisibilityTimeout}")) {
                    $sqs->updateVisibiltyTimeout($queueUrls[$queue['queue']], $newVisibilityTimeout);
                } else {
                    $this->error('Aborting.');
                    return 1;
                }
            }
        }

        $pattern = Arr::get($config, 'sites.server-name');
        $servers = $forge->getServers()->filter(function ($server) use ($pattern) {
            return preg_match("/$pattern/", $server->getName());
        });

        $sites = $servers->map(function ($server) use ($forge, $config) {
            return $forge->getSite($server, Arr::get($config, 'sites.site-name'));
        })->keyBy->getId();

        $balancingRules = collect($config['balancing'])->mapInto(Rule::class);
        $balancer = new Balancer($balancingRules);
        foreach ($sites as $site) {
            $instance = $ec2->getInstance($site->getServer()->getName());
            $balancer->addSite($site, $instance);
        }

        foreach ($queues as $queue) {
            $balancer->addQueue($queue['queue'], $queue['processes']);
        }

        $balanced = $balancer->getBalancedQueues()->map(function ($value) use ($queues) {
            return collect($value)->map(function ($count, $queue) use ($queues) {
                return new WorkerConfiguration(array_merge($queues->get($queue), ['processes' => $count]));
            });
        });

        $diffs = $sites->map(function ($site) use ($forge, $balanced) {
            return new WorkerDiff($site, $forge->getWorkers($site), $balanced->get($site->getId()));
        });

        foreach ($diffs as $diff) {
            $this->info("Updating queue configuration for: {$diff->getSite()->getServer()->getName()}");
            foreach ($diff->workersToDelete() as $worker) {
                $this->line("Deleting: {$worker->getQueue()} (Processes: {$worker->getProcesses()})");
                $forge->deleteWorker($worker);
            }

            foreach ($diff->workersToCreate() as $worker) {
                $this->line("Creating: {$worker->getQueue()} (Processes: {$worker->getProcesses()})");
                $forge->createWorker($diff->getSite(), $worker);
            }
        }
    }
}
