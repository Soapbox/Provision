<?php

namespace App\Validators;

use Closure;
use App\Forge\Forge;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Exceptions\ResourceNotFound;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ServerConfigValidator
{
    private $forge;

    public function __construct(Forge $forge)
    {
        $this->forge = $forge;
    }

    private function validateRegion(): Closure
    {
        return function ($attribute, $value, $fail) {
            if (!$this->forge->getRegions()->map->getId()->contains($value)) {
                $fail("$value is not a valid region");
            }
        };
    }

    private function validateSize(string $region): Closure
    {
        return function ($attribute, $value, $fail) use ($region) {
            $region = $this->forge->getRegion($region);
            if (!$region->getSizes()->map->getSize()->contains($value)) {
                $fail("$value is not a valid size");
            }
        };
    }

    private function validateServer(): Closure
    {
        return function ($attribute, $value, $fail) {
            try {
                $this->forge->getServer($value);
            } catch (ResourceNotFound $e) {
                $fail("$attribute does not exist");
            }
        };
    }

    private function validateScript(): Closure
    {
        return function ($attribute, $value, $fail) {
            $file = $value['script'];
            if (!Storage::disk('scripts')->exists($file)) {
                $fail("$file script does not exist");
                return;
            }

            $script = Storage::disk('scripts')->get($file);

            $matches = [];
            preg_match_all('/\{\{\w+\}\}/', $script, $matches);
            $keys = array_flip(array_map(function ($key) {
                return trim($key, '{}');
            }, $matches[0]));

            if (count($keys) != count($value['arguments']) || !empty(array_diff_key($keys, $value['arguments']))) {
                $fail("$file does not have valid arguments.");
            }
        };
    }

    private function validateNginxFile(): Closure
    {
        return function ($attribute, $value, $fail) {
            if (!Storage::disk('nginx')->exists($value)) {
                $fail("$value is not a valid nginx file.");
                return;
            }
        };
    }

    private function validateTags(): Closure
    {
        return function ($attribute, $value, $fail) {
            $key = Str::replaceFirst('tags.', '', $attribute);
            if (!is_string($key) || is_numeric($key)) {
                $fail("$attribute has non string keys");
            }
        };
    }

    public function validate(array $config): void
    {
        $rules = [
            'config' => 'required|array',
            'config.database-type' => 'present|in:mysql,mysql8,mariadb,postgres',
            'config.name' => 'required|regex:/^[a-z\-]+[a-z]$/',
            'config.php-version' => 'required|in:php56,php70,php71,php72,php73',
            'config.region' => ['required', $this->validateRegion()],
            'config.size' => ['required', $this->validateSize(Arr::get($config, 'config.region', ''))],
            'network' => 'present|array',
            'network.*' => ['required', 'string', $this->validateServer()],
            'scripts' => 'present|array',
            'scripts.*.script' => 'required',
            'scripts.*.arguments' => 'present|array',
            'scripts.*.arguments.*' => 'string',
            'scripts.*' => [$this->validateScript()],
            'sites' => 'required|array',
            'sites.*.config' => 'required|array',
            'sites.*.config.domain' => 'required|string',
            'sites.*.config.type' => 'required|in:php,html,Symfony,symfony_dev',
            'sites.*.config.aliases' => 'present|array',
            'sites.*.config.aliases.*' => 'required|string',
            'sites.*.config.directory' => 'required|string',
            'sites.*.config.wildcards' => 'required|boolean',
            'sites.*.nginx' => ['required', $this->validateNginxFile()],
            'sites.*.scripts' => 'present|array',
            'sites.*.scripts.*.script' => 'required',
            'sites.*.scripts.*.arguments' => 'present|array',
            'sites.*.scripts.*.arguments.*' => 'string',
            'sites.*.scripts.*' => [$this->validateScript()],
            'tags' => 'required|array',
            'tags.*' => ['string', $this->validateTags()],
        ];

        Validator::make($config, $rules)->validate();
    }
}
