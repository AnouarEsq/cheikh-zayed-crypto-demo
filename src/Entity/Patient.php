<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Attribute\Encrypted;

#[ORM\Entity]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text')]
    #[Encrypted(algorithm: 'aes')]
    private ?string $socialSecurityNumber = null;

    #[ORM\Column(type: 'text')]
    #[Encrypted(algorithm: 'rsa')]
    private ?string $medicalRecord = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSocialSecurityNumber(): ?string
    {
        return $this->socialSecurityNumber;
    }

    public function setSocialSecurityNumber(string $socialSecurityNumber): static
    {
        $this->socialSecurityNumber = $socialSecurityNumber;

        return $this;
    }

    public function getMedicalRecord(): ?string
    {
        return $this->medicalRecord;
    }

    public function setMedicalRecord(string $medicalRecord): static
    {
        $this->medicalRecord = $medicalRecord;

        return $this;
    }
}

