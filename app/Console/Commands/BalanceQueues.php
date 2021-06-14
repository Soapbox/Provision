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
use App\Validators\QueueConfigValidator;
use Illuminate\Validation\ValidationException;

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
    public function handle(Forge $forge, SQS $sqs, EC2 $ec2, QueueConfigValidator $validator)
    {
        $services = array_keys(config('queues'));
        $service = $this->choice('Which service would you like to balance queues for?', $services);

        $config = config("queues.$service");

        try {
            $validator->validate($config);
        } catch (ValidationException $e) {
            throw new \Exception(json_encode($e->errors(), JSON_PRETTY_PRINT));
            $this->error('The config file is invalid.');
            $this->line(json_encode($e->errors(), JSON_PRETTY_PRINT));

            return 1;
        }

        $queues = collect(Arr::get($config, 'queues'))->keyBy('queue');
        $queueUrls = $sqs->listQueues()->keyBy(fn ($url) => substr($url, strrpos($url, '/') + 1));
        $timeouts = $queueUrls->intersectByKeys($queues)->map(
            fn ($url) => $sqs->getVisibilityTimeout($url)
        );

        $queues->map(function ($queue) use ($timeouts, $sqs, $queueUrls) {
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
        });

        $pattern = Arr::get($config, 'sites.server-name');
        $servers = $forge->getServers()->filter(
            fn ($server) => preg_match("/$pattern/", $server->getName())
        );

        $sites = $servers->map(
            fn ($server) => $forge->getSite($server, Arr::get($config, 'sites.site-name'))
        )->keyBy->getId();

        $balancingRules = collect($config['balancing'])->mapInto(Rule::class);
        $balancer = new Balancer($balancingRules);
        $sites->map(
            fn ($site) => $balancer->addSite($site, $ec2->getInstance($site->getServer()->getName()))
        );

        $queues->map(fn ($queue) => $balancer->addQueue($queue['queue'], $queue['processes']));

        $balanced = $balancer->getBalancedQueues()->map(
            fn ($value) => collect($value)->map(
                fn ($count, $queue) => new WorkerConfiguration(array_merge($queues->get($queue), ['processes' => $count]))
            )
        );

        $diffs = $sites->map(
            fn ($site) => new WorkerDiff($site, $forge->getWorkers($site), $balanced->get($site->getId()))
        );

        $diffs->map(function ($diff) use ($forge) {
            $this->info("Updating queue configuration for: {$diff->getSite()->getServer()->getName()}");
            foreach ($diff->workersToDelete() as $worker) {
                $this->line("Deleting: {$worker->getQueue()} (Processes: {$worker->getProcesses()})");
                $forge->deleteWorker($worker);
            }

            foreach ($diff->workersToCreate() as $worker) {
                $this->line("Creating: {$worker->getQueue()} (Processes: {$worker->getProcesses()})");
                $forge->createWorker($diff->getSite(), $worker);
            }
        });
    }
}
