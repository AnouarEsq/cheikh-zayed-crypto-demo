<?php

namespace App\Controller;

use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestEncryptionController extends AbstractController
{
    #[Route('/test-encrypt', name: 'app_test')]
    public function index(EntityManagerInterface $em): Response
    {
        $patient = new Patient();
        $patient->setName('Jane Doe');
        $patient->setSocialSecurityNumber('ABC-123-DEF-456');

        $em->persist($patient);
        $em->flush();

        $em->clear();

        $savedPatient = $em->getRepository(Patient::class)->find($patient->getId());

        return new Response(
            "It works! <br>" .
            "Saved Name: " . htmlspecialchars($savedPatient->getName()) . "<br>" .
            "Decrypted SSN: " . htmlspecialchars($savedPatient->getSocialSecurityNumber()) . "<br><br>" .
            "<em>Check your 'var/data.db' SQLite file, the SSN is encrypted!</em>"
        );
    }
}
