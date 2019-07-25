<?php

use App\Forge\Constants\Regions;
use App\Forge\Constants\SiteTypes;
use App\Forge\Constants\PHPVersions;
use App\Forge\Constants\ServerSizes;
use App\Forge\Constants\DatabaseTypes;

return [
    'config' => [
        'database-type' => DatabaseTypes::NONE,
        'name' => 'soapbox-web',
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
        [
            'script' => 'install-datadog-apm.sh',
            'arguments' => [],
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
            'nginx' => 'soapbox-api-nginx',
            'scripts' => [
                [
                    'script' => 'logdna-configure.sh',
                    'arguments' => [
                        'directory' => 'api.goodtalk.soapboxhq.com/storage/logs',
                    ],
                ],
            ],
        ],
        [
            'config' => [
                'domain' => 'soapboxhq.com',
                'type' => SiteTypes::HTML,
                'aliases' => [],
                'directory' => '/current/dist-production',
                'wildcards' => true,
            ],
            'nginx' => 'soapbox-web-client-nginx',
            'scripts' => [],
        ],
        [
            'config' => [
                'domain' => 'goodtalk.soapboxhq.com',
                'type' => SiteTypes::HTML,
                'aliases' => [],
                'directory' => '/current/dist-production',
                'wildcards' => true,
            ],
            'nginx' => 'soapbox-web-client-nginx',
            'scripts' => [],
        ],
    ],
    'tags' => [
        'server-type' => 'api:web',
        'track-on-datadog' => 'true',
    ],
];
