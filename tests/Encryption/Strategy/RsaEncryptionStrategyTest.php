<?php

namespace App\Tests\Encryption\Strategy;

use App\Encryption\Strategy\RsaEncryptionStrategy;
use App\Secrets\LocalKeyProvider;
use PHPUnit\Framework\TestCase;

class RsaEncryptionStrategyTest extends TestCase
{
    private string $tempDir;
    private LocalKeyProvider $keyProvider;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpunit_rsa_test_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0700, true);

        $privateKeyPath = $this->tempDir . '/private.pem';
        $publicKeyPath = $this->tempDir . '/public.pem';

        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        $this->assertNotFalse($resource, 'Failed to generate RSA key pair');

        openssl_pkey_export($resource, $privateKey);
        file_put_contents($privateKeyPath, $privateKey);

        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);
        file_put_contents($publicKeyPath, $details['key']);

        $this->keyProvider = new LocalKeyProvider(
            'my-super-secret-encryption-key',
            $publicKeyPath,
            $privateKeyPath,
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
        $strategy = new RsaEncryptionStrategy($this->keyProvider);

        $plainText = 'Sensitive medical data for testing';
        $cipherText = $strategy->encryptData($plainText);

        $this->assertNotEmpty($cipherText);
        $this->assertNotSame($plainText, $cipherText);

        $decrypted = $strategy->decryptData($cipherText);
        $this->assertSame($plainText, $decrypted);
    }

    public function testFileRoundTripEncryption(): void
    {
        $strategy = new RsaEncryptionStrategy($this->keyProvider);

        $sourcePath = $this->tempDir . '/source.txt';
        $encryptedPath = $this->tempDir . '/source.txt.enc';
        $decryptedPath = $this->tempDir . '/source.txt.dec';

        file_put_contents($sourcePath, 'Sensitive medical data for file encryption.');

        $strategy->encryptFile($sourcePath, $encryptedPath);
        $strategy->decryptFile($encryptedPath, $decryptedPath);

        $this->assertFileExists($encryptedPath);
        $this->assertFileExists($decryptedPath);
        $this->assertSame(file_get_contents($sourcePath), file_get_contents($decryptedPath));
    }

    public function testStreamEncryptionRoundTrip(): void
    {
        $strategy = new RsaEncryptionStrategy($this->keyProvider);

        $sourcePath = $this->tempDir . '/stream-source.txt';
        $encryptedPath = $this->tempDir . '/stream-source.enc';
        $decryptedPath = $this->tempDir . '/stream-source.dec';

        file_put_contents($sourcePath, 'Streaming payload for RSA hybrid encryption test.');

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
        $strategy = new RsaEncryptionStrategy($this->keyProvider);
        $cipherText = $strategy->encryptData('Patient record');

        $decoded = base64_decode($cipherText, true);
        $this->assertIsString($decoded);
        $decoded[10] = $decoded[10] === "\x00" ? "\x01" : "\x00";

        $this->expectException(\Exception::class);
        $strategy->decryptData(base64_encode($decoded));
    }
}
