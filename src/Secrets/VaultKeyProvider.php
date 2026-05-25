<?php

namespace App\Secrets;

use Exception;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VaultKeyProvider implements KeyManagementInterface
{
    public function __construct(
        #[Autowire('%env(resolve:VAULT_ADDR)%')] private string $vaultAddr,
        #[Autowire('%env(resolve:VAULT_TOKEN)%')] private string $vaultToken,
        #[Autowire('%env(resolve:VAULT_AEAD_SECRET_PATH)%')] private string $aeadSecretPath,
        #[Autowire('%env(resolve:VAULT_RSA_KEY_PATH)%')] private string $rsaKeyPath,
        #[Autowire('%env(default::resolve:RSA_PASSPHRASE)%')] private ?string $rsaPassphrase = null
    ) {
        if (empty($this->vaultAddr) || empty($this->vaultToken)) {
            throw new Exception('Vault address and token are required.');
        }
    }

    public function getAesKey(): string
    {
        $secret = $this->readSecret($this->aeadSecretPath);
        return hash('sha256', $secret, true);
    }

    public function getRsaPublicKeyPath(): string
    {
        return $this->syncRsaKeyPair()[0];
    }

    public function getRsaPrivateKeyPath(): string
    {
        return $this->syncRsaKeyPair()[1];
    }

    public function getRsaPassphrase(): ?string
    {
        return $this->rsaPassphrase;
    }

    private function readSecret(string $path): string
    {
        $url = rtrim($this->vaultAddr, '/') . '/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Vault-Token: ' . $this->vaultToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception('Vault secret fetch failed: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception(sprintf('Vault returned HTTP %d for %s', $httpCode, $path));
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || !isset($payload['data']['data']['value'])) {
            throw new Exception('Vault secret payload missing expected data field.');
        }

        return (string) $payload['data']['data']['value'];
    }

    private function syncRsaKeyPair(): array
    {
        $publicKeyFile = sys_get_temp_dir() . '/vault_rsa_public.pem';
        $privateKeyFile = sys_get_temp_dir() . '/vault_rsa_private.pem';

        if (file_exists($publicKeyFile) && file_exists($privateKeyFile)) {
            return [$publicKeyFile, $privateKeyFile];
        }

        $keys = $this->readSecret($this->rsaKeyPath);
        $pair = json_decode($keys, true);
        if (!is_array($pair) || !isset($pair['public']) || !isset($pair['private'])) {
            throw new Exception('Vault RSA key payload is malformed.');
        }

        file_put_contents($publicKeyFile, $pair['public']);
        file_put_contents($privateKeyFile, $pair['private']);
        chmod($privateKeyFile, 0600);

        return [$publicKeyFile, $privateKeyFile];
    }
}
