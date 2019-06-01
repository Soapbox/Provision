<?php

namespace App;

use App\Forge\Site;
use Illuminate\Support\Facades\Storage;

class Nginx
{
    private $file;
    private $site;

    public function __construct(string $file, Site $site)
    {
        $this->file = $file;
        $this->site = $site;
    }

    public function __toString(): string
    {
        $nginx = Storage::disk('nginx')->get($this->file);
        return Replacer::replace($nginx, [
            'wildcard' => $this->site->isWildcard() ? '.' : '',
            'name' => $this->site->getName(),
        ]);
    }
}
