<?php

namespace App\Queue;

use Symfony\Component\HttpKernel\KernelInterface;

final class FilesystemEncryptionQueue implements EncryptionQueueInterface
{
    private string $queueDirectory;

    public function __construct(?KernelInterface $kernel = null, ?string $queueDirectory = null)
    {
        if ($queueDirectory !== null) {
            $this->queueDirectory = $queueDirectory;
        } elseif ($kernel !== null) {
            $this->queueDirectory = $kernel->getProjectDir() . '/var/encryption_jobs';
        } else {
            $this->queueDirectory = sys_get_temp_dir() . '/encryption_jobs';
        }

        if (!is_dir($this->queueDirectory)) {
            mkdir($this->queueDirectory, 0700, true);
        }
    }

    public function enqueue(EncryptionJob $job): string
    {
        $jobFile = sprintf('%s/encryption_job_%s.json', $this->queueDirectory, bin2hex(random_bytes(16)));
        $data = json_encode($job->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        file_put_contents($jobFile, $data);
        return $jobFile;
    }

    public function fetchPendingJobs(): array
    {
        $jobs = [];
        $files = glob($this->queueDirectory . '/encryption_job_*.json');
        if ($files === false) {
            return [];
        }

        sort($files);

        foreach ($files as $file) {
            $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $jobs[] = [
                'file' => $file,
                'job' => EncryptionJob::fromArray($payload),
            ];
        }

        return $jobs;
    }

    public function acknowledge(string $jobFile): void
    {
        if (file_exists($jobFile)) {
            @unlink($jobFile);
        }
    }
}
