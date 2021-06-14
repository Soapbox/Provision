<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use App\Forge\Constants\ServerSizes;
use JSHayes\FakeRequests\Traits\Laravel\FakeRequests;

class BalanceQueuesTest extends TestCase
{
    use FakeRequests;

    private $config = [
        'queues' => [
            'test' => [
                'sites' => [
                    'server-name' => 'test-worker-\d{3}',
                    'site-name' => 'api.test.soapboxdev.com',
                ],
                'balancing' => [
                    [
                        'server-size' => ServerSizes::T3_SMALL,
                        'max-processes' => 6,
                    ],
                    [
                        'server-size' => ServerSizes::T3_MEDIUM,
                        'max-processes' => 20,
                    ],
                ],
                'queues' => [
                    [
                        'queue' => 'queue-1',
                        'connection' => 'sqs',
                        'timeout' => 30,
                        'sleep' => 0,
                        'failed-job-delay' => 0,
                        'processes' => 15,
                        'maximum-tries' => 1,
                        'daemon' => true,
                    ],
                    [
                        'queue' => 'queue-2',
                        'connection' => 'sqs',
                        'timeout' => 70,
                        'sleep' => 0,
                        'failed-job-delay' => 0,
                        'processes' => 10,
                        'maximum-tries' => 3,
                        'daemon' => true,
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
        implode('', array_map(fn ($instance) =>
             "<item>
                <instancesSet>
                        <item>
                            <instanceId>{$instance['id']}</instanceId>
                            <keyName>{$instance['name']}</keyName>
                            <instanceType>{$instance['type']}</instanceType>
                        </item>
                </instancesSet>
            </item>", $instances)) .
        '</reservationSet>
        </DescribeInstancesResponse>';
    }

    private function getListQueuesResponse(array $queues): string
    {
        return '<ListQueuesResponse>
            <ListQueuesResult>' .
            implode(
                '',
                array_map(fn ($queue) => "<QueueUrl>https://sqs.us-west-1.amazonaws.com/123456789012/$queue</QueueUrl>", $queues)
            ) .
            '</ListQueuesResult>
            <ResponseMetadata>
                <RequestId>725275ae-0b9b-4762-b238-436d7c65a1ac</RequestId>
            </ResponseMetadata>
        </ListQueuesResponse>';
    }

    private function getQueueAttributeResponse(int $timeout): string
    {
        return "<GetQueueAttributesResponse>
            <GetQueueAttributesResult>
                <Attribute>
                    <Name>VisibilityTimeout</Name>
                    <Value>$timeout</Value>
                </Attribute>
            </GetQueueAttributesResult>
            <ResponseMetadata>
            <RequestId>1ea71be5-b5a2-4f9d-b85a-945d8d08cd0b</RequestId>
            </ResponseMetadata>
        </GetQueueAttributesResponse>";
    }

    /**
     * @test
     */
    public function test()
    {
        $handler = $this->fakeRequests();
        config($this->config);

        $queues = [
            'queue-1',
            'queue-2',
            'queue-3',
        ];
        $handler->expects('post', 'https://sqs.us-west-1.amazonaws.com')
            ->respondWith(Response::HTTP_OK, $this->getListQueuesResponse($queues))
            ->when(fn ($request) => Str::contains((string) $request->getBody(), 'Action=ListQueues'));

        $handler->expects('post', 'https://sqs.us-west-1.amazonaws.com/123456789012/queue-1')
            ->respondWith(Response::HTTP_OK, $this->getQueueAttributeResponse(60))
            ->when(
                fn ($request) => Str::contains((string) $request->getBody(), 'Action=GetQueueAttributes')
                                 && Str::contains((string) $request->getBody(), 'AttributeName.1=VisibilityTimeout')
            );

        $handler->expects('post', 'https://sqs.us-west-1.amazonaws.com/123456789012/queue-2')
            ->respondWith(Response::HTTP_OK, $this->getQueueAttributeResponse(70))
            ->when(
                fn ($request) => Str::contains((string) $request->getBody(), 'Action=GetQueueAttributes')
                                 && Str::contains((string) $request->getBody(), 'AttributeName.1=VisibilityTimeout')
            );

        $handler->expects('post', 'https://sqs.us-west-1.amazonaws.com/123456789012/queue-2')
            ->respondWith(Response::HTTP_OK, '<xml></xml>')
            ->when(
                fn ($request) => Str::contains((string) $request->getBody(), 'Action=SetQueueAttributes')
                                 && Str::contains((string) $request->getBody(), 'Attribute.1.Name=VisibilityTimeout')
                                 && Str::contains((string) $request->getBody(), 'Attribute.1.Value=100')
            );

        $servers = [
            'servers' => [
                [
                    'id' => 1,
                    'name' => 'test-worker-001',
                ],
                [
                    'id' => 2,
                    'name' => 'test-worker-002',
                ],
                [
                    'id' => 3,
                    'name' => 'test-worker-003',
                ],
                [
                    'id' => 4,
                    'name' => 'test-web-001',
                ],
                [
                    'id' => 5,
                    'name' => 'test-web-002',
                ],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(Response::HTTP_OK, $servers);

        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/1/sites')->respondWith(Response::HTTP_OK, [
            'sites' => [
                [
                    'id' => 1,
                    'name' => 'api.test.soapboxdev.com',
                ],
            ],
        ]);
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/2/sites')->respondWith(Response::HTTP_OK, [
            'sites' => [
                [
                    'id' => 2,
                    'name' => 'api.test.soapboxdev.com',
                ],
            ],
        ]);
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/3/sites')->respondWith(Response::HTTP_OK, [
            'sites' => [
                [
                    'id' => 3,
                    'name' => 'api.test.soapboxdev.com',
                ],
            ],
        ]);

        $instances = [
            ['id' => 'i-1', 'name' => 'test-worker-001', 'type' => 't3.small'],
            ['id' => 'i-2', 'name' => 'test-worker-002', 'type' => 't3.medium'],
            ['id' => 'i-3', 'name' => 'test-worker-003', 'type' => 't3.small'],
            ['id' => 'i-4', 'name' => 'test-web-001', 'type' => 't3.small'],
            ['id' => 'i-5', 'name' => 'test-web-002', 'type' => 't3.small'],
        ];
        $handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')
            ->respondWith(Response::HTTP_OK, $this->getInstancesResponse($instances))
            ->when(fn ($request) => Str::contains((string) $request->getBody(), 'Action=DescribeInstances'));

        $workers = [
            'workers' => [
                [
                    'id' => 1,
                    'queue' => 'queue-1',
                    'connection' => 'sqs',
                    'timeout' => 30,
                    'sleep' => 0,
                    'delay' => 0,
                    'processes' => 1,
                    'tries' => 1,
                    'daemon' => true,
                ],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/1/sites/1/workers')
            ->respondWitH(Response::HTTP_OK, $workers);

        $workers = [
            'workers' => [
                [
                    'id' => 2,
                    'queue' => 'queue-1',
                    'connection' => 'sqs',
                    'timeout' => 30,
                    'sleep' => 0,
                    'delay' => 0,
                    'processes' => 5,
                    'tries' => 1,
                    'daemon' => true,
                ],
                [
                    'id' => 3,
                    'queue' => 'queue-2',
                    'connection' => 'sqs',
                    'timeout' => 70,
                    'sleep' => 0,
                    'delay' => 0,
                    'processes' => 10,
                    'tries' => 1,
                    'daemon' => true,
                ],
            ],
        ];
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/2/sites/2/workers')
            ->respondWitH(Response::HTTP_OK, $workers);
        $handler->expects('get', 'https://forge.laravel.com/api/v1/servers/3/sites/3/workers')
            ->respondWitH(Response::HTTP_OK, []);

        $handler->expects('delete', 'https://forge.laravel.com/api/v1/servers/1/sites/1/workers/1')->respondWith(Response::HTTP_NO_CONTENT);
        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/1/sites/1/workers')
            ->respondWith(Response::HTTP_CREATED)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);

                return $params['queue'] == 'queue-1'
                    && $params['connection'] == 'sqs'
                    && $params['timeout'] == 30
                    && $params['sleep'] == 0
                    && $params['tries'] == 1
                    && $params['delay'] == 0
                    && $params['processes'] == 5
                    && $params['daemon'] == true;
            });
        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/1/sites/1/workers')
            ->respondWith(Response::HTTP_CREATED)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);

                return $params['queue'] == 'queue-2'
                    && $params['connection'] == 'sqs'
                    && $params['timeout'] == 70
                    && $params['sleep'] == 0
                    && $params['tries'] == 3
                    && $params['delay'] == 0
                    && $params['processes'] == 1
                    && $params['daemon'] == true;
            });

        $handler->expects('delete', 'https://forge.laravel.com/api/v1/servers/2/sites/2/workers/3')->respondWith(Response::HTTP_NO_CONTENT);
        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/2/sites/2/workers')
            ->respondWith(Response::HTTP_CREATED)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);

                return $params['queue'] == 'queue-2'
                    && $params['connection'] == 'sqs'
                    && $params['timeout'] == 70
                    && $params['sleep'] == 0
                    && $params['tries'] == 3
                    && $params['delay'] == 0
                    && $params['processes'] == 8
                    && $params['daemon'] == true;
            });

        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/3/sites/3/workers')
            ->respondWith(Response::HTTP_CREATED)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);

                return $params['queue'] == 'queue-1'
                    && $params['connection'] == 'sqs'
                    && $params['timeout'] == 30
                    && $params['sleep'] == 0
                    && $params['tries'] == 1
                    && $params['delay'] == 0
                    && $params['processes'] == 5
                    && $params['daemon'] == true;
            });
        $handler->expects('post', 'https://forge.laravel.com/api/v1/servers/3/sites/3/workers')
            ->respondWith(Response::HTTP_CREATED)
            ->when(function ($request) {
                $params = json_decode($request->getBody(), true);

                return $params['queue'] == 'queue-2'
                    && $params['connection'] == 'sqs'
                    && $params['timeout'] == 70
                    && $params['sleep'] == 0
                    && $params['tries'] == 3
                    && $params['delay'] == 0
                    && $params['processes'] == 1
                    && $params['daemon'] == true;
            });

        $this->artisan('queues:balance')
            ->expectsQuestion('Which service would you like to balance queues for?', 'test')
            ->expectsQuestion('Would you like to update the visibility timeout to 100', 'yes');
    }
}
