<?php

use App\Forge\Constants\ServerSizes;

return [
    'sites' => [
        'server-name' => 'goodtalk-worker-\d{3}',
        'site-name' => 'api.goodtalk.soapboxhq.com',
    ],
    'balancing' => [
        [
            'server-size' => 't2.medium',
            'max-processes' => 20,
        ],
        [
            'server-size' => ServerSizes::T3_SMALL,
            'max-processes' => 14,
        ],
        [
            'server-size' => ServerSizes::T3_MEDIUM,
            'max-processes' => 20,
        ],
    ],
    'queues' => [
        [
            'queue' => 'api-cron',
            'connection' => 'sqs',
            'timeout' => 600,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 3,
            'maximum-tries' => 1,
            'daemon' => true,
        ],
        [
            'queue' => 'api-default',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 2,
            'maximum-tries' => 3,
            'daemon' => true,
        ],
        [
            'queue' => 'api-email-notifications',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 15,
            'maximum-tries' => 1,
            'daemon' => true,
        ],
        [
            'queue' => 'api-notifications',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 1,
            'maximum-tries' => 1,
            'daemon' => true,
        ],
        [
            'queue' => 'api-push',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 6,
            'maximum-tries' => 3,
            'daemon' => true,
        ],
        [
            'queue' => 'api-search',
            'connection' => 'sqs',
            'timeout' => 240,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 2,
            'maximum-tries' => 3,
            'daemon' => true,
        ],
        [
            'queue' => 'api-sentry',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 3,
            'maximum-tries' => 3,
            'daemon' => true,
        ],
        [
            'queue' => 'api-subscriptions-calendar',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 7,
            'maximum-tries' => 3,
            'daemon' => true,
        ],
        [
            'queue' => 'api-webhook-queue',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 2,
            'maximum-tries' => 10,
            'daemon' => true,
        ],
    ],
];
