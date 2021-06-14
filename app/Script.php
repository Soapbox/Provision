<?php

namespace App;

use Illuminate\Support\Facades\Storage;

class Script
{
    private $code;

    public function __construct(private string $script, private array $arguments)
    {
    }

    private function getScriptFromFile(): string
    {
        $code = Storage::disk('scripts')->get($this->script);

        return Replacer::replace($code, $this->arguments);
    }

    public function getCode(): string
    {
        return $this->code = $this->code ?? $this->getScriptFromFile();
    }
}
