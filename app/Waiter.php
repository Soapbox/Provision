<?php

namespace App;

class Waiter
{
    public function waitFor(callable $callback, int $sleepSeconds): void
    {
        while ($callback()) {
            sleep($sleepSeconds);
        }
    }
}
