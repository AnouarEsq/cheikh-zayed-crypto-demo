<?php

namespace App\Encryption;

interface EncryptionStrategyInterface
{
    /**
     * The unique name of the encryption algorithm.
     */
    public static function getName(): string;

    public function encryptData(string $plainText): string;

    public function decryptData(string $encryptedData): string;

    public function encryptFile(string $sourcePath, string $destinationPath): void;

    public function decryptFile(string $sourcePath, string $destinationPath): void;
}
