<?php

namespace App\Encryption\Strategy;

use App\Encryption\EncryptionStrategyInterface;
use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: 'rsa')]
class RsaEncryptionStrategy implements EncryptionStrategyInterface
{
    public function __construct(
        #[Autowire('%env(resolve:RSA_PUBLIC_KEY_PATH)%')] private string $publicKeyPath,
        #[Autowire('%env(resolve:RSA_PRIVATE_KEY_PATH)%')] private string $privateKeyPath,
        #[Autowire('%env(default::resolve:RSA_PASSPHRASE)%')] private ?string $passphrase = null
    ) {
    }

    public static function getName(): string
    {
        return 'rsa';
    }

    public function encryptData(string $plainText): string
    {
        if (!file_exists($this->publicKeyPath)) {
            throw new Exception('RSA Public key file not found at: ' . $this->publicKeyPath);
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($this->publicKeyPath));
        if ($publicKey === false) {
            throw new Exception('Invalid RSA Public key.');
        }

        // Generate random AES key and IV
        $aesKey = openssl_random_pseudo_bytes(32);
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $tag = '';
        $encryptedData = openssl_encrypt($plainText, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        
        $encryptedAesKey = '';
        if (!openssl_public_encrypt($aesKey, $encryptedAesKey, $publicKey)) {
            throw new Exception('RSA Encryption failed. ' . openssl_error_string());
        }
        
        // Pack into a single payload
        $pack = pack('n', strlen($encryptedAesKey)) . $encryptedAesKey . $iv . $tag . $encryptedData;
        
        return base64_encode($pack);
    }

    public function decryptData(string $encryptedData): string
    {
        if (!file_exists($this->privateKeyPath)) {
            throw new Exception('RSA Private key file not found at: ' . $this->privateKeyPath);
        }

        $privateKeyContent = file_get_contents($this->privateKeyPath);
        $privateKey = openssl_pkey_get_private($privateKeyContent, $this->passphrase ?? '');
        
        if ($privateKey === false) {
            throw new Exception('Invalid RSA Private key or incorrect passphrase.');
        }

        $data = base64_decode($encryptedData);
        if (strlen($data) < 2) throw new Exception('Invalid hybrid payload');
        
        $keyLengthInfo = unpack('n', substr($data, 0, 2));
        $keyLength = $keyLengthInfo[1];
        
        $ivLength = openssl_cipher_iv_length('aes-256-gcm');
        if (strlen($data) < 2 + $keyLength + $ivLength + 16) {
            throw new Exception('Payload is too small for hybrid decryption.');
        }

        $encryptedAesKey = substr($data, 2, $keyLength);
        
        $ivOffset = 2 + $keyLength;
        $iv = substr($data, $ivOffset, $ivLength);
        
        $tagOffset = $ivOffset + $ivLength;
        $tagLength = 16;
        $tag = substr($data, $tagOffset, $tagLength);
        
        $payloadOffset = $tagOffset + $tagLength;
        $payload = substr($data, $payloadOffset);
        
        $aesKey = '';
        if (!openssl_private_decrypt($encryptedAesKey, $aesKey, $privateKey)) {
            throw new Exception('RSA Decryption failed. ' . openssl_error_string());
        }
        
        $decryptedData = openssl_decrypt($payload, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($decryptedData === false) {
            throw new Exception('Hybrid Decryption failed.');
        }
        
        return $decryptedData;
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
