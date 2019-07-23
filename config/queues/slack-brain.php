<?php

use App\Forge\Constants\ServerSizes;

return [
    'sites' => [
        'server-name' => 'brain-worker-\d{3}',
        'site-name' => 'brain.goodtalk.soapboxhq.com',
    ],
    'balancing' => [
        [
            'server-size' => ServerSizes::T2_MEDIUM,
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
            'queue' => 'brain-notifications',
            'connection' => 'sqs',
            'timeout' => 150,
            'sleep' => 0,
            'failed-job-delay' => 0,
            'processes' => 3,
            'maximum-tries' => 1,
            'daemon' => false,
        ],
    ],
];
