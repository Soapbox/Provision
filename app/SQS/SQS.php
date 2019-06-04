<?php

namespace App\SQS;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Collection;
use Aws\Handler\GuzzleV6\GuzzleHandler;
use JSHayes\FakeRequests\ClientFactory;

class SQS
{
    private $client;

    public function __construct(ClientFactory $factory)
    {
        $this->client = new SqsClient([
            'region' => 'us-west-1',
            'version' => 'latest',
            'http_handler' => new GuzzleHandler($factory->make()),
        ]);
    }

    public function listQueues(): Collection
    {
        return collect($this->client->listQueues()->get('QueueUrls'));
    }

    public function getVisibilityTimeout(string $queueUrl): int
    {
        return $this->client->getQueueAttributes([
            'QueueUrl' => $queueUrl,
            'AttributeNames' => ['VisibilityTimeout'],
        ])['Attributes']['VisibilityTimeout'];
    }

    public function updateVisibiltyTimeout(string $queueUrl, int $timeout): void
    {
        $this->client->setQueueAttributes([
            'QueueUrl' => $queueUrl,
            'Attributes' => [
                'VisibilityTimeout' => $timeout,
            ],
        ]);
    }
}
