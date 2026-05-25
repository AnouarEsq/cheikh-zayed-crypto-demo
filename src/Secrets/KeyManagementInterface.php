<?php

namespace App\Secrets;

interface KeyManagementInterface
{
    public function getAesKey(): string;

    public function getRsaPublicKeyPath(): string;

    public function getRsaPrivateKeyPath(): string;

    public function getRsaPassphrase(): ?string;
}
