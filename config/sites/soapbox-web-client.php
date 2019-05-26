<?php

return [
    'web' => [
        'servers' => [
            'soapbox-web-001',
            'soapbox-web-002',
        ],
        'sites' => [
            [
                'domain' => 'soapboxhq.com',
                'type' => 'html',
                'aliases' => [],
                'directory' => '/current/dist-production',
                'wildcards' => true,
            ],
            [
                'domain' => 'goodtalk.soapboxhq.com',
                'type' => 'html',
                'aliases' => [],
                'directory' => '/current/dist-production',
                'wildcards' => true,
            ],
        ],
        'network' => [],
        'nginx' => 'soapbox-web-client-nginx',
        'server-scripts' => [],
        'tags' => [],
    ],
];
