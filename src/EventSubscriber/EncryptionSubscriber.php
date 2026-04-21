<?php

namespace App\EventSubscriber;

use App\Attribute\Encrypted;
use App\Service\EncryptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use ReflectionClass;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postLoad)]
class EncryptionSubscriber
{
    public function __construct(private EncryptionService $encryptionService) {}

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->processFields($args->getObject(), 'encrypt');
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->processFields($args->getObject(), 'encrypt');
    }

    public function postLoad(LifecycleEventArgs $args): void
    {
        $this->processFields($args->getObject(), 'decrypt');
    }

    private function processFields(object $entity, string $action): void
    {
        $reflectionClass = new ReflectionClass(get_class($entity));
        
        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Encrypted::class);
            
            if (!empty($attributes)) {
                $attributeInstance = $attributes[0]->newInstance();
                $algorithm = $attributeInstance->algorithm;

                $property->setAccessible(true);
                $value = $property->getValue($entity);
                
                if (is_string($value) && $value !== '') {
                    if ($action === 'encrypt') {
                        $property->setValue($entity, $this->encryptionService->encryptData($value, $algorithm));
                    } elseif ($action === 'decrypt') {
                        // RSA encrypted base64 string could contain different characters or be longer
                        if ($this->isBase64($value)) {
                            $property->setValue($entity, $this->encryptionService->decryptData($value, $algorithm));
                        }
                    }
                }
            }
        }
    }

    private function isBase64(string $string): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string);
    }
}
