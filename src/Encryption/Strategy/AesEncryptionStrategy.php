<?php

namespace App\Encryption\Strategy;

use App\Encryption\EncryptionStrategyInterface;
use App\Encryption\Model\EncryptedPayload;
use App\Secrets\KeyManagementInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'aes')]
class AesEncryptionStrategy implements EncryptionStrategyInterface
{
    private const CIPHER_ALGO = 'aes-256-gcm';

    public function __construct(
        private KeyManagementInterface $keyProvider
    ) {
    }

    public static function getName(): string
    {
        return 'aes';
    }

    public function encryptData(string $plainText): string
    {
        $key = $this->keyProvider->getAesKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plainText,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new Exception('AES-GCM encryption failed.');
        }

        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }

        return EncryptedPayload::pack(self::CIPHER_ALGO, '', $iv, $tag, $ciphertext);
    }

    public function decryptData(string $encryptedData): string
    {
        $payload = EncryptedPayload::unpack($encryptedData);
        if ($payload['algorithm'] !== self::CIPHER_ALGO) {
            throw new Exception('Mismatched encryption algorithm.');
        }

        $key = $this->keyProvider->getAesKey();
        $decrypted = openssl_decrypt(
            $payload['ciphertext'], 
            self::CIPHER_ALGO, 
            $key, 
            OPENSSL_RAW_DATA, 
            $payload['iv'], 
            $payload['tag']
        );

        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }

        if ($decrypted === false) {
            throw new Exception('Decryption failed for AES.');
        }

        return $decrypted;
    }

    public function encryptStream($input, $output): void
    {
        $plainText = stream_get_contents($input);
        if ($plainText === false) {
            throw new Exception('Unable to read plaintext from input stream.');
        }

        $encryptedPayload = $this->encryptData($plainText);
        if (fwrite($output, $encryptedPayload) === false) {
            throw new Exception('Unable to write encrypted payload to output stream.');
        }
    }

    public function decryptStream($input, $output): void
    {
        $payload = stream_get_contents($input);
        if ($payload === false) {
            throw new Exception('Unable to read encrypted payload from input stream.');
        }

        $decrypted = $this->decryptData($payload);
        if (fwrite($output, $decrypted) === false) {
            throw new Exception('Unable to write decrypted plaintext to output stream.');
        }
    }

    public function encryptFile(string $sourcePath, string $destinationPath): void
    {
        if (!file_exists($sourcePath)) {
            throw new Exception('File not found: ' . $sourcePath);
        }

        $input = fopen($sourcePath, 'rb');
        if ($input === false) {
            throw new Exception('Unable to open source file for reading.');
        }

        $output = fopen($destinationPath, 'wb');
        if ($output === false) {
            fclose($input);
            throw new Exception('Unable to open destination file for writing.');
        }

        try {
            $this->encryptStream($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function decryptFile(string $sourcePath, string $destinationPath): void
    {
        if (!file_exists($sourcePath)) {
            throw new Exception('File not found: ' . $sourcePath);
        }

        $input = fopen($sourcePath, 'rb');
        if ($input === false) {
            throw new Exception('Unable to open source file for reading.');
        }

        $output = fopen($destinationPath, 'wb');
        if ($output === false) {
            fclose($input);
            throw new Exception('Unable to open destination file for writing.');
        }

        try {
            $this->decryptStream($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }
    }
}
