<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Validators\QueueConfigValidator;
use App\Validators\ServerConfigValidator;
use Illuminate\Validation\ValidationException;

class ValidateConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'validate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all config files';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(ServerConfigValidator $serverValidator, QueueConfigValidator $queueValidator)
    {
        foreach (array_keys(config('servers')) as $service) {
            foreach (array_keys(config("servers.$service")) as $server) {
                $this->info("Validating: servers.$service.$server");
                try {
                    $serverValidator->validate(config("servers.$service.$server"));
                } catch (ValidationException $e) {
                    $this->line(json_encode($e->validator->errors(), JSON_PRETTY_PRINT));
                }
            }
        }

        foreach (array_keys(config('queues')) as $queue) {
            $this->info("Validating: queues.$queue");
            try {
                $queueValidator->validate(config("queues.$queue"));
            } catch (ValidationException $e) {
                $this->line(json_encode($e->validator->errors(), JSON_PRETTY_PRINT));
            }
        }
    }
}
