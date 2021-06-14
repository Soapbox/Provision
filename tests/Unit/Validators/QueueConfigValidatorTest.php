<?php

namespace Tests\Unit\Validators;

use Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Forge\Constants\ServerSizes;
use App\Validators\QueueConfigValidator;
use Illuminate\Validation\ValidationException;
use JSHayes\FakeRequests\Traits\Laravel\FakeRequests;

class QueueConfigValidatorTest extends TestCase
{
    use FakeRequests;

    private $valid = [
        'sites' => [
            'server-name' => 'test-worker-\d{3}',
            'site-name' => 'test.soapboxdev.com',
        ],
        'balancing' => [
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
                'queue' => 'queue-1',
                'connection' => 'sqs',
                'timeout' => 600,
                'sleep' => 0,
                'failed-job-delay' => 0,
                'processes' => 3,
                'maximum-tries' => 1,
                'daemon' => true,
            ],
            [
                'queue' => 'queue-2',
                'connection' => 'sqs',
                'timeout' => 150,
                'sleep' => 0,
                'failed-job-delay' => 0,
                'processes' => 2,
                'maximum-tries' => 3,
                'daemon' => true,
            ],
        ],
    ];

    private function overwrite(array $data): array
    {
        $result = $this->valid;
        foreach ($data as $key => $value) {
            Arr::set($result, $key, $value);
        }

        return $result;
    }

    private function without(array $data): array
    {
        $result = $this->valid;
        foreach ($data as $key) {
            Arr::pull($result, $key);
        }

        return $result;
    }

    private function assertIsValid(array $config): void
    {
        try {
            resolve(QueueConfigValidator::class)->validate($config);
        } catch (ValidationException $e) {
            $this->fail("Validation failed\n" . json_encode($e->errors(), JSON_PRETTY_PRINT));
        }

        $this->assertTrue(true);
    }

    private function assertIsNotValid(array $config): void
    {
        try {
            resolve(QueueConfigValidator::class)->validate($config);
        } catch (ValidationException $e) {
            $this->assertTrue(true);

            return;
        }

        $this->fail();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->fakeRequests();

        $this->mockForgeServerApiCall();
        $this->mockForgeSiteApiCalls();
        $this->mockEc2ApiCall();
    }

    private function mockForgeServerApiCall(): void
    {
        $this->handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(200, [
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
                    'name' => 'test-web-001',
                ],
            ],
        ]);
    }

    private function mockForgeSiteApiCalls(): void
    {
        $this->handler->expects('get', 'https://forge.laravel.com/api/v1/servers/1/sites')->respondWith(200, [
            'sites' => [
                [
                    'id' => 1,
                    'name' => 'test.soapboxdev.com',
                ],
            ],
        ]);

        $this->handler->expects('get', 'https://forge.laravel.com/api/v1/servers/2/sites')->respondWith(200, [
            'sites' => [
                [
                    'id' => 2,
                    'name' => 'test.soapboxdev.com',
                ],
            ],
        ]);
    }

    private function mockEc2ApiCall(): void
    {
        $instances = [
            ['id' => 'i-1', 'name' => 'test-worker-001', 'type' => 't3.small'],
            ['id' => 'i-2', 'name' => 'test-worker-002', 'type' => 't3.medium'],
        ];
        $this->handler->expects('post', 'https://ec2.us-west-1.amazonaws.com')->respondWith(
            200,
            $this->getInstancesResponse($instances)
        )->when(function ($request) {
            return Str::contains((string) $request->getBody(), 'Action=DescribeInstances');
        });
    }

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
                            <instanceType>{$instance['type']}</instanceType>
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
    public function it_passes_validation_when_the_input_is_valid()
    {
        $this->assertIsValid($this->valid);
    }

    /**
     * @test
     */
    public function the_sites_key_is_required()
    {
        $this->fakeRequests();
        $this->assertIsNotValid($this->without(['sites']));
    }

    /**
     * @test
     */
    public function the_sites_server_name_is_required_and_must_be_a_string()
    {
        $this->assertIsValid($this->overwrite(['sites.server-name' => 'test-worker-\d+']));
        $this->assertIsNotValid($this->overwrite(['sites.server-name' => '']));
        $this->assertIsNotValid($this->overwrite(['sites.server-name' => null]));
        $this->assertIsNotValid($this->overwrite(['sites.server-name' => 1]));
        $this->assertIsNotValid($this->without(['sites.server-name']));
    }

    /**
     * @test
     */
    public function the_sites_site_name_is_required_and_must_be_a_string()
    {
        $this->assertIsValid($this->overwrite(['sites.site-name' => 'test.soapboxdev.com']));
        $this->assertIsNotValid($this->overwrite(['sites.site-name' => '']));
        $this->assertIsNotValid($this->overwrite(['sites.site-name' => null]));
        $this->assertIsNotValid($this->overwrite(['sites.site-name' => 1]));
        $this->assertIsNotValid($this->without(['sites.site-name']));
    }

    /**
     * @test
     */
    public function it_fails_validation_when_there_are_no_matching_servers()
    {
        $this->handler = $this->fakeRequests();
        $this->mockForgeServerApiCall();
        $this->assertIsNotValid($this->overwrite(['sites.server-name' => 'invalid']));
    }

    /**
     * @test
     */
    public function it_fails_validation_when_the_site_does_not_exist_for_the_server()
    {
        $this->handler = $this->fakeRequests();
        $this->mockForgeServerApiCall();
        $this->mockForgeSiteApiCalls();
        $this->assertIsNotValid($this->overwrite(['sites.site-name' => 'invalid']));
    }

    /**
     * @test
     */
    public function the_balancing_key_is_required()
    {
        $this->handler = $this->fakeRequests();
        $this->mockForgeServerApiCall();
        $this->mockForgeSiteApiCalls();
        $this->assertIsNotValid($this->without(['balancing']));
    }

    /**
     * @test
     */
    public function it_fails_when_not_every_server_type_has_balancing_defined_for_it()
    {
        $this->assertIsNotValid($this->without(['balancing.0']));
    }

    /**
     * @test
     */
    public function it_fails_validation_when_the_balancing_server_sizes_are_not_unique()
    {
        $this->assertIsNotValid($this->overwrite([
            'balancing.0.server-size' => 't3.small',
            'balancing.1.server-size' => 't3.small',
        ]));
    }

    /**
     * @test
     */
    public function the_balancing_max_processes_must_be_at_least_1()
    {
        $this->assertIsValid($this->overwrite(['balancing.0.max-processes' => 1]));
        $this->assertIsNotValid($this->overwrite(['balancing.0.max-processes' => 0]));
    }

    /**
     * @test
     */
    public function the_queues_key_is_required()
    {
        $this->assertIsNotValid($this->without(['queues']));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.queue' => 'valid-queue']));
        $this->assertIsNotValid($this->overwrite(['queues.0.queue' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.queue' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.queue' => 1]));
        $this->assertIsNotValid($this->overwrite([
            'queues.0.queue' => 'queue-name',
            'queues.1.queue' => 'queue-name',
        ]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_connection_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.connection' => 'sqs']));
        $this->assertIsValid($this->overwrite(['queues.0.connection' => 'redis']));
        $this->assertIsNotValid($this->overwrite(['queues.0.connection' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.connection' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.connection' => 1]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_timeout_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.timeout' => 1]));
        $this->assertIsValid($this->overwrite(['queues.0.timeout' => 60]));
        $this->assertIsNotValid($this->overwrite(['queues.0.timeout' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.timeout' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.timeout' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.timeout' => 0]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_sleep_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.sleep' => 0]));
        $this->assertIsValid($this->overwrite(['queues.0.sleep' => 60]));
        $this->assertIsNotValid($this->overwrite(['queues.0.sleep' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.sleep' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.sleep' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.sleep' => -1]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_failed_job_delay_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.failed-job-delay' => 0]));
        $this->assertIsValid($this->overwrite(['queues.0.failed-job-delay' => 60]));
        $this->assertIsNotValid($this->overwrite(['queues.0.failed-job-delay' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.failed-job-delay' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.failed-job-delay' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.failed-job-delay' => -1]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_processes_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.processes' => 1]));
        $this->assertIsValid($this->overwrite(['queues.0.processes' => 60]));
        $this->assertIsNotValid($this->overwrite(['queues.0.processes' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.processes' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.processes' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.processes' => 0]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_maximum_tries_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.maximum-tries' => 0]));
        $this->assertIsValid($this->overwrite(['queues.0.maximum-tries' => 60]));
        $this->assertIsNotValid($this->overwrite(['queues.0.maximum-tries' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.maximum-tries' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.maximum-tries' => null]));
        $this->assertIsNotValid($this->overwrite(['queues.0.maximum-tries' => -1]));
    }

    /**
     * @test
     */
    public function it_passes_when_the_queue_daemon_is_valid()
    {
        $this->assertIsValid($this->overwrite(['queues.0.daemon' => true]));
        $this->assertIsValid($this->overwrite(['queues.0.daemon' => false]));
        $this->assertIsNotValid($this->overwrite(['queues.0.daemon' => 'test']));
        $this->assertIsNotValid($this->overwrite(['queues.0.daemon' => '']));
        $this->assertIsNotValid($this->overwrite(['queues.0.daemon' => null]));
    }
}
