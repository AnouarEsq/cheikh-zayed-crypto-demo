<?php

namespace App\Encryption\Strategy;

use App\Encryption\EncryptionStrategyInterface;
use App\Encryption\Model\EncryptedPayload;
use App\Secrets\KeyManagementInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'rsa')]
class RsaEncryptionStrategy implements EncryptionStrategyInterface
{
    private const HYBRID_ALGORITHM = 'RSA-OAEP-AES-256-GCM';

    public function __construct(
        private KeyManagementInterface $keyProvider
    ) {
    }

    public static function getName(): string
    {
        return 'rsa';
    }

    public function encryptData(string $plainText): string
    {
        $publicKeyPath = $this->keyProvider->getRsaPublicKeyPath();
        if (!file_exists($publicKeyPath)) {
            throw new Exception('RSA Public key file not found at: ' . $publicKeyPath);
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
        if ($publicKey === false) {
            throw new Exception('Invalid RSA Public key.');
        }

        $aesKey = random_bytes(32);
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        $iv = random_bytes($ivLength);

        $tag = '';
        $ciphertext = openssl_encrypt($plainText, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($aesKey);
            }
            throw new Exception('AES-GCM encryption failed. ' . openssl_error_string());
        }

        if (!defined('OPENSSL_PKCS1_OAEP_PADDING')) {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($aesKey);
            }
            throw new Exception('OAEP padding constant not available in this PHP build.');
        }

        $encryptedAesKey = '';
        if (!openssl_public_encrypt($aesKey, $encryptedAesKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($aesKey);
            }
            throw new Exception('RSA Encryption (OAEP) failed. ' . openssl_error_string());
        }

        if (function_exists('sodium_memzero')) {
            sodium_memzero($aesKey);
        }

        return EncryptedPayload::pack(self::HYBRID_ALGORITHM, $encryptedAesKey, $iv, $tag, $ciphertext);
    }

    public function decryptData(string $encryptedData): string
    {
        $payload = EncryptedPayload::unpack($encryptedData);
        if ($payload['algorithm'] !== self::HYBRID_ALGORITHM) {
            throw new Exception('Mismatched encryption algorithm.');
        }

        $privateKeyPath = $this->keyProvider->getRsaPrivateKeyPath();
        if (!file_exists($privateKeyPath)) {
            throw new Exception('RSA Private key file not found at: ' . $privateKeyPath);
        }

        $privateKeyContent = file_get_contents($privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyContent, $this->keyProvider->getRsaPassphrase() ?? '');
        if ($privateKey === false) {
            throw new Exception('Invalid RSA Private key or incorrect passphrase.');
        }

        if (!defined('OPENSSL_PKCS1_OAEP_PADDING')) {
            throw new Exception('OAEP padding constant not available in this PHP build.');
        }

        $aesKey = '';
        if (!openssl_private_decrypt($payload['encrypted_key'], $aesKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new Exception('RSA Decryption (OAEP) failed. ' . openssl_error_string());
        }

        $decrypted = openssl_decrypt($payload['ciphertext'], 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $payload['iv'], $payload['tag']);
        if (function_exists('sodium_memzero')) {
            sodium_memzero($aesKey);
        }

        if ($decrypted === false) {
            throw new Exception('Hybrid Decryption failed.');
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
