<?php

namespace App;

use Illuminate\Support\Facades\Storage;

class Script
{
    private $code;
    private $script;
    private $arguments;

    public function __construct(string $script, array $arguments)
    {
        $this->script = $script;
        $this->arguments = $arguments;
    }

    private function getScriptFromFile(): string
    {
        $code = Storage::disk('scripts')->get($this->script);

        $keys = array_map(function ($key) {
            return '{' . $key . '}';
        }, array_keys($this->arguments));

        return str_replace($keys, $this->arguments, $code);
    }

    public function getCode(): string
    {
        return $this->code = $this->code ?? $this->getScriptFromFile();
    }
}
