<?php

namespace App\Tests\Queue;

use App\Queue\EncryptionJob;
use App\Queue\FilesystemEncryptionQueue;
use PHPUnit\Framework\TestCase;

class FilesystemEncryptionQueueTest extends TestCase
{
    private string $queueDir;

    protected function setUp(): void
    {
        $this->queueDir = sys_get_temp_dir() . '/phpunit_encryption_queue_' . bin2hex(random_bytes(8));
        mkdir($this->queueDir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->queueDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->queueDir);
    }

    public function testEnqueueAndFetchPendingJobs(): void
    {
        $queue = new FilesystemEncryptionQueue(null, $this->queueDir);
        $job = new EncryptionJob(
            EncryptionJob::ACTION_ENCRYPT,
            $this->queueDir . '/source.txt',
            $this->queueDir . '/destination.enc',
            'aes'
        );

        $jobFile = $queue->enqueue($job);
        $this->assertFileExists($jobFile);

        $pending = $queue->fetchPendingJobs();
        $this->assertCount(1, $pending);
        $this->assertSame($jobFile, $pending[0]['file']);
        $this->assertInstanceOf(EncryptionJob::class, $pending[0]['job']);
        $this->assertSame(EncryptionJob::ACTION_ENCRYPT, $pending[0]['job']->getAction());
    }

    public function testAcknowledgeRemovesJobFile(): void
    {
        $queue = new FilesystemEncryptionQueue(null, $this->queueDir);
        $job = new EncryptionJob(
            EncryptionJob::ACTION_DECRYPT,
            $this->queueDir . '/source.enc',
            $this->queueDir . '/destination.txt',
            'rsa'
        );

        $jobFile = $queue->enqueue($job);
        $this->assertFileExists($jobFile);

        $queue->acknowledge($jobFile);
        $this->assertFileDoesNotExist($jobFile);
    }
}
