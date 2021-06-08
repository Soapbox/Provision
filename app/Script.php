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

        return Replacer::replace($code, $this->arguments);
    }

    public function getCode(): string
    {
        return $this->code = $this->code ?? $this->getScriptFromFile();
    }
}
