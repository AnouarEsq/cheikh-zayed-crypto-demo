<?php

namespace App\Queue;

interface EncryptionQueueInterface
{
    public function enqueue(EncryptionJob $job): string;

    public function fetchPendingJobs(): array;

    public function acknowledge(string $jobFile): void;
}
