<?php

namespace Tests\Unit\Console\Commands;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Str;
use App\Forge\Constants\SiteTypes;
use App\Forge\Constants\PHPVersions;
use Illuminate\Filesystem\Filesystem;
use App\Forge\Constants\DatabaseTypes;
use Illuminate\Support\Facades\Storage;
use JSHayes\FakeRequests\Traits\Laravel\FakeRequests;

class CreateServerTest extends TestCase
{
    use FakeRequests;

    private $config = [
        'servers' => [
            'test' => [
                'web' => [
                    'config' => [
                        'database-type' => DatabaseTypes::NONE,
                        'name' => 'test-web',
                        'php-version' => PHPVersions::PHP72,
                        'region' => 'us-west-1',
                        'size' => 't3.small',
                        'max-upload-size' => 10,
                    ],
                    'network' => [
                        'test-database-001',
                        'test-redis-001',
                    ],
                    'scripts' => [
                        [
                            'script' => 'install-datadog-agent',
                            'arguments' => [
                                'key' => 'datadog-key',
                            ],
                        ],
                        [
                            'script' => 'install-logdna-agent',
                            'arguments' => [
                                'key' => 'logdna-key',
                            ],
                        ],
                    ],
                    'sites' => [
                        [
                            'config' => [
                                'domain' => 'api.test.soapboxdev.com',
                                'type' => SiteTypes::PHP,
                                'aliases' => [],
                                'directory' => '/public/current',
                                'wildcards' => false,
                            ],
                            'nginx' => 'test-api-nginx',
                            'scripts' => [
                                [
                                    'script' => 'logdna-track-directory',
                                    'arguments' => [
                                        'directory' => 'api.goodtalk.soapboxhq.com/storage/logs',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'config' => [
                                'domain' => 'soapboxdev.com',
                                'type' => SiteTypes::HTML,
                                'aliases' => [],
                                'directory' => '/current/dist-production',
                                'wildcards' => true,
                            ],
                            'nginx' => 'test-web-client-nginx',
                            'scripts' => [],
                        ],
                        [
                            'config' => [
                                'domain' => 'no-nginx.soapboxdev.com',
                                'type' => SiteTypes::HTML,
                                'aliases' => [],
                                'directory' => '/current/dist-production',
                                'wildcards' => true,
                            ],
                            'nginx' => null,
                            'scripts' => [],
                        ],
                    ],
                    'tags' => [
                        'server-type' => 'test:web',
                        'track-on-datadog' => 'true',
                    ],
                ],
            ],
        ],
    ];

    private function getInstancesResponse(array $instances): string
    {
        return '<DescribeInstancesResponse xmlns="http://ec2.amazonaws.com/doc/2016-11-15/">
        <requestId>8f7724cf-496f-496e-8fe3-example</requestId>
        <reservationSet>' .
        implode('', array_map(function ($instance) {
            return "<item>
                <instancesSet>
                        <item>
                            <instanceId>{$instance['id']}</instanceId>
                            <keyName>{$instance['name']}</keyName>
                        </item>
                </instancesSet>
            </item>";
        }, $instances)) .
        '</reservationSet>
        </DescribeInstancesResponse>';
    }

    /**
     * @test
     */
    public function test()
    {
        $handler = $this->fakeRequests();
        config($this->config);

        $handler->expects('get', 'https://forge.laravel.com/api/v1/regions')->respondWith(200, [
            'regions' => [
                'aws' => [
                    [
                        'id' => 'us-east-1',
                        'sizes' => [
                            ['id' => 0, 'size' => 't3.small'],
                            ['id' => 1, 'size' => 't3.medium'],
                        ],
                    ],
                    [
                        'id' => 'us-west-1',
                        'sizes' => [
                            ['id' => 0, 'size' => 't3.small'],
                            ['id' => 1, 'size' => 't3.medium'],
                        ],
                    ],
                ],
            ],
        ]);

        $instances = [
            ['id' => 'i-1', 'name' => 'test-web-001'],
            ['id' => 'i-2', 'name' => 'test-web-002'],
            ['id' => 'i-3', 'name' => 'test-web-003'],
            ['id' => 'i-4', 'name' => 'test-web-004'],
        ];
        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(
            200,
            $this->getInstancesResponse($instances)
        )->when(function ($request) {
            return Str::contains((string) $request->getBody(), 'Action=DescribeInstances');
        });

        $servers = [
            'servers' => [
                [
                    'id' => 1,
                    'name' => 'test-web-001',
                ],
                [
                    'id' => 2,
                    'name' => 'test-web-002',
                ],
                [
                    'id' => 3,
                    'name' => 'test-web-003',
                ],
                [
                    'id' => 4,
                    'name' => 'test-web-004',
                ],
                [
                    'id' => 5,
                    'name' => 'test-database-001',
                ],
                [
                    'id' => 6,
                    'name' => 'test-redis-001',
                ],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, $servers);

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, [
            'server' => [
                'id' => 10,
                'name' => 'test-web-005',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['name'] == 'test-web-005'
                && $params['size'] == '0'
                && $params['region'] == 'us-west-1'
                && $params['php_version'] == 'php72'
                && $params['database_type'] == ''
                && $params['network'] == [5, 6];
        });
        $servers['servers'][] = [
            'id' => 10,
            'name' => 'test-web-005',
            'is_ready' => true,
        ];

        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, $servers);

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10')->respondWith(200, [
            'server' => [
                'id' => 10,
                'name' => 'test-web-005',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['max_upload_size'] == 10;
        });

        $instances[] = ['id' => 'i-5', 'name' => 'test-web-005'];
        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(
            200,
            $this->getInstancesResponse($instances)
        )->when(function ($request) {
            return Str::contains((string) $request->getBody(), 'Action=DescribeInstances');
        });

        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(200, '<xml></xml>')->when(function ($request) {
            $params = urldecode((string) $request->getBody());
            return Str::contains($params, 'Action=ModifyInstanceCreditSpecification')
                && Str::contains($params, 'InstanceCreditSpecification.1.CpuCredits=standard')
                && Str::contains($params, 'InstanceCreditSpecification.1.InstanceId=i-5');
        });

        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(200, '<xml></xml>')->when(function ($request) {
            $params = urldecode((string) $request->getBody());
            return Str::contains($params, 'Action=CreateTags')
                && Str::contains($params, 'ResourceId.1=i-5')
                && Str::contains($params, 'Tag.1.Key=server-type')
                && Str::contains($params, 'Tag.1.Value=test:web')
                && Str::contains($params, 'Tag.2.Key=track-on-datadog')
                && Str::contains($params, 'Tag.2.Value=true');
        });

        $sites = [
            'sites' => [
                ['id' => 1, 'name' => 'default'],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $handler->expects('delete', 'https://forge.laravel.com/api/v1/servers/10/sites/1')->respondWith(204);

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 2,
                'name' => 'api.test.soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'api.test.soapboxdev.com'
                && $params['project_type'] == 'php'
                && $params['aliases'] == []
                && $params['directory'] == '/public/current'
                && $params['wildcards'] == false;
        });

        $sites['sites'][] = ['id' => 2, 'name' => 'api.test.soapboxdev.com', 'status' => 'installed', 'wildcards' => false];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $nginx = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')->with('nginx')->andReturn($nginx);
        $nginx->shouldReceive('exists')->with('test-api-nginx')->andReturn(true);
        $nginx->shouldReceive('get')->with('test-api-nginx')->andReturn('nginx {{wildcard}}{{name}}');

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10/sites/2/nginx')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['content'] == 'nginx api.test.soapboxdev.com';
            });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 3,
                'name' => 'soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'soapboxdev.com'
                && $params['project_type'] == 'html'
                && $params['aliases'] == []
                && $params['directory'] == '/current/dist-production'
                && $params['wildcards'] == true;
        });

        $sites['sites'][] = ['id' => 3, 'name' => 'soapboxdev.com', 'status' => 'installed', 'wildcards' => true];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $nginx->shouldReceive('exists')->with('test-web-client-nginx')->andReturn(true);
        $nginx->shouldReceive('get')->with('test-web-client-nginx')->andReturn('nginx {{wildcard}}{{name}}');

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10/sites/3/nginx')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['content'] == 'nginx .soapboxdev.com';
            });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 4,
                'name' => 'no-nginx.soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'no-nginx.soapboxdev.com'
                && $params['project_type'] == 'html'
                && $params['aliases'] == []
                && $params['directory'] == '/current/dist-production'
                && $params['wildcards'] == true;
        });

        $sites['sites'][] = ['id' => 4, 'name' => 'no-nginx.soapboxdev.com', 'status' => 'installed', 'wildcards' => true];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $scripts = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')->with('scripts')->andReturn($scripts);

        $scripts->shouldReceive('exists')->with('install-datadog-agent')->andReturn(true);
        $scripts->shouldReceive('exists')->with('install-logdna-agent')->andReturn(true);
        $scripts->shouldReceive('exists')->with('logdna-track-directory')->andReturn(true);
        $scripts->shouldReceive('get')->with('install-datadog-agent')->andReturn('datadog script: {{key}}');
        $scripts->shouldReceive('get')->with('install-logdna-agent')->andReturn('logdna script: {{key}}');
        $scripts->shouldReceive('get')->with('logdna-track-directory')->andReturn('logdna track script: {{directory}}');

        $handler->expects('post', 'https://forge.laravel.com/api/v1/recipes')->respondWith(200, [
            'recipe' => ['id' => 1],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return array_key_exists('name', $params)
                && $params['user'] == 'root'
                && $params['script'] == "datadog script: datadog-key\nlogdna script: logdna-key\nlogdna track script: api.goodtalk.soapboxhq.com/storage/logs";
        });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/recipes/1/run')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['servers'] = [10];
            });

        $this->artisan('server:create')
            ->expectsQuestion('Which service would you like to create a server for?', 'test')
            ->expectsQuestion('Which server type would you like to create?', 'web')
            ->expectsQuestion('Are you sure you want to create this server?', 'yes');
    }

    /**
     * @test
     */
    public function test_it_names_the_first_server_001()
    {
        $handler = $this->fakeRequests();
        config($this->config);

        $handler->expects('get', 'https://forge.laravel.com/api/v1/regions')->respondWith(200, [
            'regions' => [
                'aws' => [
                    [
                        'id' => 'us-east-1',
                        'sizes' => [
                            ['id' => 0, 'size' => 't3.small'],
                            ['id' => 1, 'size' => 't3.medium'],
                        ],
                    ],
                    [
                        'id' => 'us-west-1',
                        'sizes' => [
                            ['id' => 0, 'size' => 't3.small'],
                            ['id' => 1, 'size' => 't3.medium'],
                        ],
                    ],
                ],
            ],
        ]);

        $instances = [
        ];
        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(
            200,
            $this->getInstancesResponse($instances)
        )->when(function ($request) {
            return Str::contains((string) $request->getBody(), 'Action=DescribeInstances');
        });

        $servers = [
            'servers' => [
                [
                    'id' => 5,
                    'name' => 'test-database-001',
                ],
                [
                    'id' => 6,
                    'name' => 'test-redis-001',
                ],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, $servers);

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, [
            'server' => [
                'id' => 10,
                'name' => 'test-web-001',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['name'] == 'test-web-001'
                && $params['size'] == '0'
                && $params['region'] == 'us-west-1'
                && $params['php_version'] == 'php72'
                && $params['database_type'] == ''
                && $params['network'] == [5, 6];
        });
        $servers['servers'][] = [
            'id' => 10,
            'name' => 'test-web-001',
            'is_ready' => true,
        ];

        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, $servers);

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10')->respondWith(200, [
            'server' => [
                'id' => 10,
                'name' => 'test-web-001',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['max_upload_size'] == 10;
        });

        $instances[] = ['id' => 'i-5', 'name' => 'test-web-001'];
        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(
            200,
            $this->getInstancesResponse($instances)
        )->when(function ($request) {
            return Str::contains((string) $request->getBody(), 'Action=DescribeInstances');
        });

        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(200, '<xml></xml>')->when(function ($request) {
            $params = urldecode((string) $request->getBody());
            return Str::contains($params, 'Action=ModifyInstanceCreditSpecification')
                && Str::contains($params, 'InstanceCreditSpecification.1.CpuCredits=standard')
                && Str::contains($params, 'InstanceCreditSpecification.1.InstanceId=i-5');
        });

        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(200, '<xml></xml>')->when(function ($request) {
            $params = urldecode((string) $request->getBody());
            return Str::contains($params, 'Action=CreateTags')
                && Str::contains($params, 'ResourceId.1=i-5')
                && Str::contains($params, 'Tag.1.Key=server-type')
                && Str::contains($params, 'Tag.1.Value=test:web')
                && Str::contains($params, 'Tag.2.Key=track-on-datadog')
                && Str::contains($params, 'Tag.2.Value=true');
        });

        $sites = [
            'sites' => [
                ['id' => 1, 'name' => 'default'],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $handler->expects('delete', 'https://forge.laravel.com/api/v1/servers/10/sites/1')->respondWith(204);

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 2,
                'name' => 'api.test.soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'api.test.soapboxdev.com'
                && $params['project_type'] == 'php'
                && $params['aliases'] == []
                && $params['directory'] == '/public/current'
                && $params['wildcards'] == false;
        });

        $sites['sites'][] = ['id' => 2, 'name' => 'api.test.soapboxdev.com', 'status' => 'installed', 'wildcards' => false];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $nginx = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')->with('nginx')->andReturn($nginx);
        $nginx->shouldReceive('exists')->with('test-api-nginx')->andReturn(true);
        $nginx->shouldReceive('get')->with('test-api-nginx')->andReturn('nginx {{wildcard}}{{name}}');

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10/sites/2/nginx')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['content'] == 'nginx api.test.soapboxdev.com';
            });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 3,
                'name' => 'soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'soapboxdev.com'
                && $params['project_type'] == 'html'
                && $params['aliases'] == []
                && $params['directory'] == '/current/dist-production'
                && $params['wildcards'] == true;
        });

        $sites['sites'][] = ['id' => 3, 'name' => 'soapboxdev.com', 'status' => 'installed', 'wildcards' => true];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $nginx->shouldReceive('exists')->with('test-web-client-nginx')->andReturn(true);
        $nginx->shouldReceive('get')->with('test-web-client-nginx')->andReturn('nginx {{wildcard}}{{name}}');

        $handler->expects('put', 'https://forge.laravel.com/api/v1/servers/10/sites/3/nginx')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['content'] == 'nginx .soapboxdev.com';
            });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, [
            'site' => [
                'id' => 4,
                'name' => 'no-nginx.soapboxdev.com',
            ],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return $params['domain'] == 'no-nginx.soapboxdev.com'
                && $params['project_type'] == 'html'
                && $params['aliases'] == []
                && $params['directory'] == '/current/dist-production'
                && $params['wildcards'] == true;
        });

        $sites['sites'][] = ['id' => 4, 'name' => 'no-nginx.soapboxdev.com', 'status' => 'installed', 'wildcards' => true];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/10/sites')->respondWith(200, $sites);

        $scripts = Mockery::mock(Filesystem::class);
        Storage::shouldReceive('disk')->with('scripts')->andReturn($scripts);

        $scripts->shouldReceive('exists')->with('install-datadog-agent')->andReturn(true);
        $scripts->shouldReceive('exists')->with('install-logdna-agent')->andReturn(true);
        $scripts->shouldReceive('exists')->with('logdna-track-directory')->andReturn(true);
        $scripts->shouldReceive('get')->with('install-datadog-agent')->andReturn('datadog script: {{key}}');
        $scripts->shouldReceive('get')->with('install-logdna-agent')->andReturn('logdna script: {{key}}');
        $scripts->shouldReceive('get')->with('logdna-track-directory')->andReturn('logdna track script: {{directory}}');

        $handler->expects('post', 'https://forge.laravel.com/api/v1/recipes')->respondWith(200, [
            'recipe' => ['id' => 1],
        ])->when(function ($request) {
            $params = json_decode($request->getBody(), true);
            return array_key_exists('name', $params)
                && $params['user'] == 'root'
                && $params['script'] == "datadog script: datadog-key\nlogdna script: logdna-key\nlogdna track script: api.goodtalk.soapboxhq.com/storage/logs";
        });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/recipes/1/run')
            ->respondWith(200)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);
                return $params['servers'] = [10];
            });

        $this->artisan('server:create')
            ->expectsQuestion('Which service would you like to create a server for?', 'test')
            ->expectsQuestion('Which server type would you like to create?', 'web')
            ->expectsQuestion('Are you sure you want to create this server?', 'yes');
    }
}
