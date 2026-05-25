<?php

namespace App\Secrets;

use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LocalKeyProvider implements KeyManagementInterface
{
    public function __construct(
        #[Autowire('%env(resolve:ENCRYPTION_KEY)%')] private string $encryptionKey,
        #[Autowire('%env(resolve:RSA_PUBLIC_KEY_PATH)%')] private string $rsaPublicKeyPath,
        #[Autowire('%env(resolve:RSA_PRIVATE_KEY_PATH)%')] private string $rsaPrivateKeyPath,
        #[Autowire('%env(default::resolve:RSA_PASSPHRASE)%')] private ?string $rsaPassphrase = null
    ) {
        if (empty($this->encryptionKey)) {
            throw new Exception('ENCRYPTION_KEY is required for local key provider.');
        }

        if (!file_exists($this->rsaPublicKeyPath) || !file_exists($this->rsaPrivateKeyPath)) {
            throw new Exception('RSA key files must exist for local key provider.');
        }
    }

    public function getAesKey(): string
    {
        return hash('sha256', $this->encryptionKey, true);
    }

    public function getRsaPublicKeyPath(): string
    {
        return $this->rsaPublicKeyPath;
    }

    public function getRsaPrivateKeyPath(): string
    {
        return $this->rsaPrivateKeyPath;
    }

    public function getRsaPassphrase(): ?string
    {
        return $this->rsaPassphrase;
    }
}
