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

    /**
     * Stream-based encryption: read from $input, write encrypted payload to $output.
     * @param resource $input
     * @param resource $output
     */
    public function encryptStream($input, $output): void;

    /**
     * Stream-based decryption: read encrypted payload from $input, write plaintext to $output.
     * @param resource $input
     * @param resource $output
     */
    public function decryptStream($input, $output): void;

    public function encryptFile(string $sourcePath, string $destinationPath): void;

    public function decryptFile(string $sourcePath, string $destinationPath): void;
}
