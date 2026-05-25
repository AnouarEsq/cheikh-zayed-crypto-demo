<?php

namespace App\Tests\Encryption\Strategy;

use App\Encryption\Strategy\AesEncryptionStrategy;
use App\Secrets\LocalKeyProvider;
use PHPUnit\Framework\TestCase;

class AesEncryptionStrategyTest extends TestCase
{
    private string $tempDir;
    private LocalKeyProvider $keyProvider;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpunit_aes_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0700, true);

        $privateKey = openssl_pkey_new(['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($privateKey, $privateKeyPem);
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'];

        file_put_contents($this->tempDir . '/private.pem', $privateKeyPem);
        file_put_contents($this->tempDir . '/public.pem', $publicKeyPem);

        $this->keyProvider = new LocalKeyProvider(
            'my-super-secret-encryption-key',
            $this->tempDir . '/public.pem',
            $this->tempDir . '/private.pem',
            null
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/private.pem');
        @unlink($this->tempDir . '/public.pem');
        @rmdir($this->tempDir);
    }

    public function testRoundTripEncryption(): void
    {
        $strategy = new AesEncryptionStrategy($this->keyProvider);

        $plainText = 'Confidential medical payload';
        $cipherText = $strategy->encryptData($plainText);

        $this->assertNotEmpty($cipherText);
        $this->assertNotSame($plainText, $cipherText);

        $decrypted = $strategy->decryptData($cipherText);
        $this->assertSame($plainText, $decrypted);
    }

    public function testFileRoundTripEncryption(): void
    {
        $strategy = new AesEncryptionStrategy($this->keyProvider);

        $sourcePath = $this->tempDir . '/source.txt';
        $encryptedPath = $this->tempDir . '/source.txt.enc';
        $decryptedPath = $this->tempDir . '/source.txt.dec';

        file_put_contents($sourcePath, 'Confidential medical payload for file encryption.');

        $strategy->encryptFile($sourcePath, $encryptedPath);
        $strategy->decryptFile($encryptedPath, $decryptedPath);

        $this->assertFileExists($encryptedPath);
        $this->assertFileExists($decryptedPath);
        $this->assertSame(file_get_contents($sourcePath), file_get_contents($decryptedPath));
    }

    public function testStreamEncryptionRoundTrip(): void
    {
        $strategy = new AesEncryptionStrategy($this->keyProvider);

        $sourcePath = $this->tempDir . '/stream-source.txt';
        $encryptedPath = $this->tempDir . '/stream-source.enc';
        $decryptedPath = $this->tempDir . '/stream-source.dec';

        file_put_contents($sourcePath, 'Streaming payload for AES encryption test.');

        $input = fopen($sourcePath, 'rb');
        $output = fopen($encryptedPath, 'wb');
        $strategy->encryptStream($input, $output);
        fclose($input);
        fclose($output);

        $input = fopen($encryptedPath, 'rb');
        $output = fopen($decryptedPath, 'wb');
        $strategy->decryptStream($input, $output);
        fclose($input);
        fclose($output);

        $this->assertSame(file_get_contents($sourcePath), file_get_contents($decryptedPath));
    }

    public function testTamperedPayloadFails(): void
    {
        $strategy = new AesEncryptionStrategy($this->keyProvider);
        $plainText = 'Protected patient information';
        $cipherText = $strategy->encryptData($plainText);

        $decoded = base64_decode($cipherText, true);
        $this->assertIsString($decoded);

        $decoded[5] = $decoded[5] === "\x00" ? "\x01" : "\x00";
        $tampered = base64_encode($decoded);

        $this->expectException(\Exception::class);
        $strategy->decryptData($tampered);
    }
}
