<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:generate-rsa-keys',
    description: 'Generates RSA public and private keys for testing encryption.',
)]
class GenerateRsaKeysCommand extends Command
{
    private string $projectDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $keysDir = $this->projectDir . '/config/jwt';
        if (!is_dir($keysDir)) {
            mkdir($keysDir, 0777, true);
        }

        $privateKeyPath = $keysDir . '/private.pem';
        $publicKeyPath = $keysDir . '/public.pem';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            $io->warning('Keys already exist. If you want to regenerate them, delete them first.');
            return Command::SUCCESS;
        }

        $minimalCnf = $keysDir . '/minimal_openssl.cnf';
        $config = array(
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        
        if (file_exists($minimalCnf)) {
            $config['config'] = $minimalCnf;
        }

        $res = openssl_pkey_new($config);
        
        if ($res === false) {
            $io->error('Failed to generate key pair. ' . openssl_error_string());
            return Command::FAILURE;
        }

        openssl_pkey_export($res, $privKey, null, $config);
        file_put_contents($privateKeyPath, $privKey);

        $pubKey = openssl_pkey_get_details($res);
        if ($pubKey === false) {
            $io->error('Failed to extract public key.');
            return Command::FAILURE;
        }
        
        file_put_contents($publicKeyPath, $pubKey["key"]);
        
        // Ensure proper permissions
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        $io->success('RSA Keys successfully generated in config/jwt/ !');

        return Command::SUCCESS;
    }
}
