<?php
use App\SiteTypes;

return [
    'web' => [
        'servers' => [
            'soapbox-web-001',
            'soapbox-web-002',
        ],
        'sites' => [
            [
                'domain' => 'api.goodtalk.soapboxhq.com',
                'type' => SiteTypes::PHP,
                'aliases' => [],
                'directory' => '/public/current',
                'wildcards' => false,
                'scripts' => [
                    // Scripts::trackLogDnaDirectory(),
                ],
            ],
        ],
        'network' => [
            'database-001',
            'redis-queue-001',
        ],
        'nginx' => 'soapbox-api-nginx',
        'server-scripts' => [
            // Scripts::installLogDNA(),
            // Scripts::installDataDog(),
        ],
        'tags' => [
            'track-on-datadog' => true,
            'server-type' => 'api:web',
        ],
    ],
    'worker' => [
        'servers' => [
            'goodtalk-worker-001',
            'goodtalk-worker-002',
            'goodtalk-worker-003',
        ],
        'sites' => [
            [
                'domain' => 'api.goodtalk.soapboxhq.com',
                'type' => SiteTypes::PHP,
                'aliases' => [],
                'directory' => '/public/current',
                'wildcards' => false,
            ],
        ],
        'network' => [
            'database-001',
            'redis-queue-001',
        ],
    ],
];