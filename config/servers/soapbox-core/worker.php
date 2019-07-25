<?php

use App\Forge\Constants\SiteTypes;
use App\Forge\Constants\PHPVersions;
use App\Forge\Constants\DatabaseTypes;

return [
    'config' => [
        'database-type' => DatabaseTypes::NONE,
        'name' => 'goodtalk-worker',
        'php-version' => PHPVersions::PHP72,
        'region' => 'us-west-1',
        'size' => 't3.medium',
        'max-upload-size' => null,
    ],
    'network' => [
        'database-001',
        'redis-queue-001',
    ],
    'scripts' => [
        [
            'script' => 'install-datadog-agent.sh',
            'arguments' => [
                'key' => env('DATADOG_KEY'),
            ],
        ],
        [
            'script' => 'install-logdna-agent.sh',
            'arguments' => [
                'key' => env('LOGDNA_KEY'),
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
            'nginx' => null,
            'scripts' => [
                [
                    'script' => 'logdna-configure.sh',
                    'arguments' => [
                        'directory' => 'api.goodtalk.soapboxhq.com/storage/logs',
                    ],
                ],
            ],
        ],
    ],
    'tags' => [
        'server-type' => 'api:worker',
        'track-on-datadog' => 'true',
    ],
];
