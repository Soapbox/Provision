<?php

namespace Tests\Unit\Validators;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Http\Response;
use App\Forge\Constants\SiteTypes;
use App\Forge\Constants\PHPVersions;
use Illuminate\Filesystem\Filesystem;
use App\Forge\Constants\DatabaseTypes;
use Illuminate\Support\Facades\Storage;
use App\Validators\ServerConfigValidator;
use Illuminate\Validation\ValidationException;
use JSHayes\FakeRequests\Traits\Laravel\FakeRequests;

class ServerConfigValidatorTest extends TestCase
{
    use FakeRequests;

    private $valid = [
        'config' => [
            'database-type' => DatabaseTypes::NONE,
            'name' => 'soapbox-web',
            'php-version' => PHPVersions::PHP73,
            'region' => 'us-west-1',
            'size' => 't3.small',
            'max-upload-size' => 10,
        ],
        'network' => [],
        'scripts' => [],
        'sites' => [
            [
                'config' => [
                    'domain' => 'api.goodtalk.soapboxhq.com',
                    'type' => SiteTypes::PHP,
                    'aliases' => [],
                    'directory' => '/public/current',
                    'wildcards' => false,
                ],
                'nginx' => 'valid-nginx',
                'scripts' => [],
            ],
        ],
        'tags' => [
            'server-type' => 'api:web',
            'track-on-datadog' => 'true',
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
            resolve(ServerConfigValidator::class)->validate($config);
        } catch (ValidationException $e) {
            $this->fail("Validation failed\n" . json_encode($e->errors(), JSON_PRETTY_PRINT));
        }

        $this->assertTrue(true);
    }

    private function assertIsNotValid(array $config): void
    {
        try {
            resolve(ServerConfigValidator::class)->validate($config);
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
        $this->handler->expects('get', 'https://forge.laravel.com/api/v1/regions')->respondWith(Response::HTTP_OK, [
            'regions' => [
                'aws' => [
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
        $this->validScripts = [];
        $this->validNginxFiles = ['valid-nginx'];

        $this->scripts = Mockery::mock(Filesystem::class);
        $this->scripts->shouldReceive('exists')->andReturnUsing(
            fn ($file) => array_key_exists($file, $this->validScripts)
        )->zeroOrMoreTimes();
        $this->scripts->shouldReceive('get')->andReturnUsing(
            fn ($file) => $this->validScripts[$file]
        )->zeroOrMoreTimes();
        Storage::shouldReceive('disk')->with('scripts')->andReturn($this->scripts)->zeroOrMoreTimes();

        $this->nginx = Mockery::mock(Filesystem::class);
        $this->nginx->shouldReceive('exists')->andReturnUsing(
            fn ($file) => in_array($file, $this->validNginxFiles)
        )->zeroOrMoreTimes();
        Storage::shouldReceive('disk')->with('nginx')->andReturn($this->nginx)->zeroOrMoreTimes();
    }

    private function fakeScript(string $file, string $code): void
    {
        $this->validScripts[$file] = $code;
    }

    /**
     * @test
     */
    public function the_config_key_is_required()
    {
        $this->fakeRequests();
        $this->assertIsNotValid($this->without(['config']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_config_database_type_is_correct()
    {
        $this->assertIsValid($this->overwrite(['config.database-type' => 'mysql']));
        $this->assertIsValid($this->overwrite(['config.database-type' => 'mysql8']));
        $this->assertIsValid($this->overwrite(['config.database-type' => 'mariadb']));
        $this->assertIsValid($this->overwrite(['config.database-type' => 'postgres']));
        $this->assertIsNotValid($this->overwrite(['config.database-type' => 'wat']));
        $this->assertIsNotValid($this->without(['config.database-type']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_config_name_is_correct()
    {
        $this->assertIsValid($this->overwrite(['config.name' => 'test']));
        $this->assertIsValid($this->overwrite(['config.name' => 'test-server']));
        $this->assertIsValid($this->overwrite(['config.name' => 'big-test-server']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'TEST-SERVER']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test_server']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test-server-001']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test-server-{number}']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test1-server']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test-server!']));
        $this->assertIsNotValid($this->overwrite(['config.name' => 'test-server-']));
        $this->assertIsNotValid($this->without(['config.name']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_config_php_version_is_correct()
    {
        $this->assertIsValid($this->overwrite(['config.php-version' => 'php56']));
        $this->assertIsValid($this->overwrite(['config.php-version' => 'php70']));
        $this->assertIsValid($this->overwrite(['config.php-version' => 'php71']));
        $this->assertIsValid($this->overwrite(['config.php-version' => 'php72']));
        $this->assertIsValid($this->overwrite(['config.php-version' => 'php73']));
        $this->assertIsNotValid($this->overwrite(['config.php-version' => 'php74']));
        $this->assertIsNotValid($this->overwrite(['config.php-version' => 'wat']));
        $this->assertIsNotValid($this->without(['config.php-version']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_config_region_is_correct()
    {
        $this->fakeRequests()->expects('get', 'https://forge.laravel.com/api/v1/regions')->respondWith(Response::HTTP_OK, [
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

        $this->assertIsValid($this->overwrite(['config.region' => 'us-west-1']));
        $this->assertIsValid($this->overwrite(['config.region' => 'us-east-1']));
        $this->assertIsNotValid($this->overwrite(['config.region' => 'us-west-2']));
        $this->assertIsNotValid($this->overwrite(['config.region' => 'us-east-2']));
        $this->assertIsNotValid($this->without(['config.region']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_config_size_is_correct()
    {
        $this->fakeRequests()->expects('get', 'https://forge.laravel.com/api/v1/regions')->respondWith(Response::HTTP_OK, [
            'regions' => [
                'aws' => [
                    [
                        'id' => 'us-west-1',
                        'sizes' => [
                            ['id' => 0, 'size' => 't3.micro'],
                            ['id' => 0, 'size' => 't3.small'],
                            ['id' => 1, 'size' => 't3.medium'],
                            ['id' => 1, 'size' => 't3.large'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertIsValid($this->overwrite(['config.size' => 't3.micro']));
        $this->assertIsValid($this->overwrite(['config.size' => 't3.small']));
        $this->assertIsValid($this->overwrite(['config.size' => 't3.medium']));
        $this->assertIsValid($this->overwrite(['config.size' => 't3.large']));
        $this->assertIsNotValid($this->overwrite(['config.size' => 't2.medium']));
        $this->assertIsNotValid($this->overwrite(['config.size' => 'wat']));
        $this->assertIsNotValid($this->without(['config.size']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_max_upload_size_is_valid()
    {
        $this->assertIsValid($this->overwrite(['config.max-upload-size' => 1]));
        $this->assertIsValid($this->overwrite(['config.max-upload-size' => 100]));
        $this->assertIsValid($this->overwrite(['config.max-upload-size' => null]));
        $this->assertIsNotValid($this->overwrite(['config.max-upload-size' => 'test']));
        $this->assertIsNotValid($this->overwrite(['config.max-upload-size' => 0]));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_network_is_contains_only_valid_servers()
    {
        $this->handler->expects('get', 'https://forge.laravel.com/api/v1/servers')->respondWith(Response::HTTP_OK, [
            'servers' => [
                ['id' => 1, 'name' => 'server-001'],
                ['id' => 2, 'name' => 'server-002'],
            ],
        ]);
        $this->assertIsValid($this->overwrite(['network' => []]));
        $this->assertIsValid($this->overwrite(['network' => ['server-001', 'server-002']]));
        $this->assertIsNotValid($this->overwrite(['network' => ['']]));
        $this->assertIsNotValid($this->overwrite(['network' => ['server-003']]));
        $this->assertIsNotValid($this->without(['network']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_scripts_contains_valid_script_configurations()
    {
        $this->fakeScript('valid-script', 'Script {{key1}} and {{key2}}');
        $this->assertIsValid($this->overwrite(['scripts' => []]));
        $this->assertIsValid($this->overwrite(['scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ]]]));
        $this->assertIsValid($this->overwrite(['scripts' => [
            [
                'script' => 'valid-script',
                'arguments' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ],
            [
                'script' => 'valid-script',
                'arguments' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ],
        ]]));
        $this->assertIsNotValid($this->overwrite(['scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 'value1',
            ],
        ]]]));
        $this->assertIsNotValid($this->overwrite(['scripts' => [[
            'script' => 'invalid-script',
            'arguments' => [],
        ]]]));
        $this->assertIsNotValid($this->overwrite(['scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 1,
                'key2' => 'value2',
            ],
        ]]]));
        $this->assertIsNotValid($this->without(['scripts']));
    }

    /**
     * @test
     */
    public function the_sites_key_is_required_and_cannot_be_empty()
    {
        $this->assertIsNotValid($this->without(['sites']));
        $this->assertIsNotValid($this->overwrite(['sites' => []]));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_config_domain_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.config.domain' => 'api.test.com']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.domain' => 1]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.domain' => null]));
        $this->assertIsNotValid($this->without(['sites.0.config.domain']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_config_type_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.config.type' => 'php']));
        $this->assertIsValid($this->overwrite(['sites.0.config.type' => 'html']));
        $this->assertIsValid($this->overwrite(['sites.0.config.type' => 'Symfony']));
        $this->assertIsValid($this->overwrite(['sites.0.config.type' => 'symfony_dev']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.type' => 'wat']));
        $this->assertIsNotValid($this->without(['sites.0.config.type']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_config_aliases_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.config.aliases' => ['api.test.com']]));
        $this->assertIsValid($this->overwrite(['sites.0.config.aliases' => []]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.aliases' => ['']]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.aliases' => [null]]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.aliases' => null]));
        $this->assertIsNotValid($this->without(['sites.0.config.aliases']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_config_directory_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.config.directory' => '/test/dir']));
        $this->assertIsValid($this->overwrite(['sites.0.config.directory' => '/wat']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.directory' => ['/wat']]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.directory' => '']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.directory' => null]));
        $this->assertIsNotValid($this->without(['sites.0.config.directory']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_config_wildcards_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.config.wildcards' => true]));
        $this->assertIsValid($this->overwrite(['sites.0.config.wildcards' => false]));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.wildcards' => 'true']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.wildcards' => 'false']));
        $this->assertIsNotValid($this->overwrite(['sites.0.config.wildcards' => null]));
        $this->assertIsNotValid($this->without(['sites.0.config.wildcards']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_nginx_is_valid()
    {
        $this->assertIsValid($this->overwrite(['sites.0.nginx' => 'valid-nginx']));
        $this->assertIsValid($this->overwrite(['sites.0.nginx' => null]));
        $this->assertIsValid($this->overwrite(['sites.0.nginx' => '']));
        $this->assertIsNotValid($this->overwrite(['sites.0.nginx' => 'invalid-nginx']));
        $this->assertIsNotValid($this->without(['sites.0.nginx']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_sites_scripts_contains_valid_script_configurations()
    {
        $this->fakeScript('valid-script', 'Script {{key1}} and {{key2}}');
        $this->assertIsValid($this->overwrite(['sites.0.scripts' => []]));
        $this->assertIsValid($this->overwrite(['sites.0.scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ]]]));
        $this->assertIsValid($this->overwrite(['sites.0.scripts' => [
            [
                'script' => 'valid-script',
                'arguments' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ],
            [
                'script' => 'valid-script',
                'arguments' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
            ],
        ]]));
        $this->assertIsNotValid($this->overwrite(['sites.0.scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 'value1',
            ],
        ]]]));
        $this->assertIsNotValid($this->overwrite(['sites.0.scripts' => [[
            'script' => 'invalid-script',
            'arguments' => [],
        ]]]));
        $this->assertIsNotValid($this->overwrite(['sites.0.scripts' => [[
            'script' => 'valid-script',
            'arguments' => [
                'key1' => 1,
                'key2' => 'value2',
            ],
        ]]]));
        $this->assertIsNotValid($this->without(['sites.0.scripts']));
    }

    /**
     * @test
     */
    public function it_passes_validation_when_the_tags_are_valid()
    {
        $this->assertIsValid($this->overwrite(['tags' => ['key' => 'value']]));
        $this->assertIsValid($this->overwrite(['tags' => ['key-1' => 'value-1']]));
        $this->assertIsValid($this->overwrite(['tags' => ['key-2' => 'value-2']]));
        $this->assertIsNotValid($this->overwrite(['tags' => [1 => 'value']]));
        $this->assertIsNotValid($this->overwrite(['tags' => ['key' => true]]));
        $this->assertIsNotValid($this->overwrite(['tags' => ['key' => null]]));
        $this->assertIsNotValid($this->overwrite(['tags' => ['key' => 1]]));
        $this->assertIsNotValid($this->without(['tags']));
    }
}
