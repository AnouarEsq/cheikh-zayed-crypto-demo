<?php

namespace App\Encryption\Strategy;

use App\Encryption\EncryptionStrategyInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'aes')]
class AesEncryptionStrategy implements EncryptionStrategyInterface
{
    private const CIPHER_ALGO = 'aes-256-gcm';
    private string $key;

    public function __construct(
        #[Autowire('%env(resolve:ENCRYPTION_KEY)%')] string $encryptionKey
    ) {
        $this->key = hash('sha256', $encryptionKey, true);
    }

    public static function getName(): string
    {
        return 'aes';
    }

    public function encryptData(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $plainText, 
            self::CIPHER_ALGO, 
            $this->key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }

    public function decryptData(string $encryptedData): string
    {
        $data = base64_decode($encryptedData);
        $ivLength = openssl_cipher_iv_length(self::CIPHER_ALGO);
        $tagLength = 16;
        
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $payload = substr($data, $ivLength + $tagLength);
        
        $decrypted = openssl_decrypt(
            $payload, 
            self::CIPHER_ALGO, 
            $this->key, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed for AES.');
        }
        
        return $decrypted;
    }

    public function encryptFile(string $sourcePath, string $destinationPath): void
    {
        if (!file_exists($sourcePath)) {
            throw new Exception("File not found: " . $sourcePath);
        }
        $fileContent = file_get_contents($sourcePath);
        $encryptedContent = $this->encryptData($fileContent);
        file_put_contents($destinationPath, $encryptedContent);
    }

    public function decryptFile(string $sourcePath, string $destinationPath): void
    {
        if (!file_exists($sourcePath)) {
            throw new Exception("File not found: " . $sourcePath);
        }
        $encryptedContent = file_get_contents($sourcePath);
        $decryptedContent = $this->decryptData($encryptedContent);
        file_put_contents($destinationPath, $decryptedContent);
    }
}
