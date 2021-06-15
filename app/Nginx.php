<?php

namespace App;

use App\Forge\Site;
use Illuminate\Support\Facades\Storage;

class Nginx
{
    public function __construct(private string $file, private Site $site)
    {
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
