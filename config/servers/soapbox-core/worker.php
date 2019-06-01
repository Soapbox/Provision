<?php

use App\Forge\Constants\SiteTypes;
use App\Forge\Constants\PHPVersions;
use App\Forge\Constants\DatabaseTypes;

return [
    'config' => [
        'database_type' => DatabaseTypes::NONE,
        'name' => 'goodtalk-worker-{number}',
        'php_version' => PHPVersions::PHP72,
        'region' => 'us-west-1',
        'size' => 't3.medium',
    ],
    'network' => [
        'database-001',
        'redis-queue-001',
    ],
    'scripts' => [
        [
            'script' => 'install-datadog-agent',
            'arguments' => [
                'key' => env(''),
            ],
        ],
        [
            'script' => 'install-logdna-agent',
            'arguments' => [
                'key' => env(''),
            ],
        ],
    ],
    'sites' => [
        [
            'config' => [
                'domain' => 'api.goodtalk.soapboxhq.com',
                'type' => SiteTypes::PHP,
                'aliases' => [],
                'directory' => '/public/current',
                'wildcards' => false,
            ],
            'load-balance' => false,
            'nginx' => 'soapbox-api-nginx',
            'scripts' => [
                [
                    'script' => 'logdna-configure',
                    'arguments' => [
                        'directory' => 'api.goodtalk.soapboxhq.com/storage/logs',
                        'tags' => 'api,worker',
                    ],
                ],
            ],
        ],
    ],
    'tags' => [
        'server-type' => 'api:worker',
        'track-on-datadog' => true,
    ],
];
