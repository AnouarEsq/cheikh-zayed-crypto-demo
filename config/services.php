<?php

use App\Encryption\Strategy\AesEncryptionStrategy;
use App\Encryption\Strategy\RsaEncryptionStrategy;
use App\Queue\EncryptionQueueInterface;
use App\Queue\FilesystemEncryptionQueue;
use App\Secrets\KeyManagementInterface;
use App\Secrets\LocalKeyProvider;
use App\Service\EncryptionService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $config): void {
    $services = $config->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->public(false);

    $services->load('App\\', '../src/')
        ->exclude(['../src/DependencyInjection', '../src/Entity', '../src/Migrations', '../src/Tests']);

    $services->set(LocalKeyProvider::class);
    $services->alias(KeyManagementInterface::class, LocalKeyProvider::class)->public();

    $services->set(FilesystemEncryptionQueue::class);
    $services->alias(EncryptionQueueInterface::class, FilesystemEncryptionQueue::class);

    $services->set(EncryptionService::class)
        ->args([tagged_iterator('app.encryption_strategy')]);

    $services->set(AesEncryptionStrategy::class)
        ->tag('app.encryption_strategy', ['index' => 'aes']);

    $services->set(RsaEncryptionStrategy::class)
        ->tag('app.encryption_strategy', ['index' => 'rsa']);
};
