<?php

namespace App\Queue;

use Exception;

final class EncryptionJob
{
    public const ACTION_ENCRYPT = 'encrypt';
    public const ACTION_DECRYPT = 'decrypt';

    public function __construct(
        private string $action,
        private string $sourcePath,
        private string $destinationPath,
        private string $algorithm = 'aes'
    ) {
        if (!in_array($this->action, [self::ACTION_ENCRYPT, self::ACTION_DECRYPT], true)) {
            throw new Exception(sprintf('Unsupported encryption job action: %s', $this->action));
        }

        if ($this->sourcePath === '' || $this->destinationPath === '') {
            throw new Exception('Encryption job must include both source and destination paths.');
        }
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'source_path' => $this->sourcePath,
            'destination_path' => $this->destinationPath,
            'algorithm' => $this->algorithm,
        ];
    }

    public static function fromArray(array $data): self
    {
        if (!isset($data['action'], $data['source_path'], $data['destination_path'], $data['algorithm'])) {
            throw new Exception('Malformed encryption job payload.');
        }

        return new self(
            (string) $data['action'],
            (string) $data['source_path'],
            (string) $data['destination_path'],
            (string) $data['algorithm']
        );
    }
}
