<?php

use App\Forge\Constants\DatabaseTypes;
use App\Forge\Constants\PHPVersions;
use App\Forge\Constants\Regions;
use App\Forge\Constants\ServerSizes;
use App\Forge\Constants\SiteTypes;

return [
    'config' => [
        'database-type' => DatabaseTypes::NONE,
        'name' => 'goodtalk-worker',
        'php-version' => PHPVersions::PHP72,
        'region' => Regions::N_CALIFORNIA,
        'size' => ServerSizes::T3_SMALL,
        'max-upload-size' => 10,
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
