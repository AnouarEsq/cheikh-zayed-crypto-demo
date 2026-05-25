<?php

namespace App\Service;

use App\Encryption\EncryptionStrategyInterface;
use App\Queue\EncryptionJob;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class EncryptionService
{
    /**
     * @var array<string, EncryptionStrategyInterface>
     */
    private array $strategies = [];

    public function __construct(
        #[TaggedIterator('app.encryption_strategy')] iterable $strategies
    ) {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy::getName()] = $strategy;
        }
    }

    public function getStrategy(string $algorithm): EncryptionStrategyInterface
    {
        if (!isset($this->strategies[$algorithm])) {
            throw new Exception("Encryption algorithm '$algorithm' is not supported.");
        }
        
        return $this->strategies[$algorithm];
    }

    public function encryptData(string $plainText, string $algorithm = 'aes'): string
    {
        return $this->getStrategy($algorithm)->encryptData($plainText);
    }

    public function decryptData(string $encryptedData, string $algorithm = 'aes'): string
    {
        return $this->getStrategy($algorithm)->decryptData($encryptedData);
    }

    public function processJob(EncryptionJob $job): void
    {
        if ($job->getAction() === EncryptionJob::ACTION_ENCRYPT) {
            $this->encryptFile($job->getSourcePath(), $job->getDestinationPath(), $job->getAlgorithm());
            return;
        }

        if ($job->getAction() === EncryptionJob::ACTION_DECRYPT) {
            $this->decryptFile($job->getSourcePath(), $job->getDestinationPath(), $job->getAlgorithm());
            return;
        }

        throw new Exception('Unsupported encryption job action: ' . $job->getAction());
    }

    public function encryptFile(string $sourcePath, string $destinationPath, string $algorithm = 'aes'): void
    {
        $this->getStrategy($algorithm)->encryptFile($sourcePath, $destinationPath);
    }

    public function decryptFile(string $sourcePath, string $destinationPath, string $algorithm = 'aes'): void
    {
        $this->getStrategy($algorithm)->decryptFile($sourcePath, $destinationPath);
    }
}
