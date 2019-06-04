<?php

namespace App;

interface WorkerInterface
{
    public function getConnection(): string;

    public function getQueue(): string;

    public function getTimeout(): int;

    public function getDelay(): int;

    public function getSleep(): int;

    public function getTries(): int;

    public function getProcesses(): int;

    public function isDaemon(): bool;
}
