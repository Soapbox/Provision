<?php

use App\Forge\Forge;

return [
    'web' => [
        'name' => 'soapbox-web-{number}',
        'size' => 't3.small',
        'region' => 'us-west-1',
        'database_type' => Forge::DATABASE_NONE,
        'php_version' => Forge::PHP_72,
        'aws_vpc_id' => 'vpc-341f2751',
        'aws_subnet_id' => 'subnet-bcbfb7e5',
        'sites' => [
            'soapbox-api.web',
            'soapbox-web-client.web',
        ],
    ],
    'worker' => [
        'name' => 'goodtalk-worker-{number}',
        'size' => 't3.medium',
        'region' => 'us-west-1',
        'database_type' => Forge::DATABASE_NONE,
        'php_version' => Forge::PHP_72,
        'aws_vpc_id' => 'vpc-341f2751',
        'aws_subnet_id' => 'subnet-bcbfb7e5',
        'sites' => [
            'soapbox-api.worker',
        ],
    ],
];
