# Architecture et Implémentation du Module de Chiffrement

Ce document synthétise les concepts cryptographiques et architecturaux mis en œuvre pour le module de chiffrement du système de gestion hospitalière.

## 1. Patron de Conception : Stratégie (Strategy Pattern)
*   **Concept :** Ce *design pattern* comportemental permet de définir une famille d'algorithmes (les différentes méthodes de chiffrement), de les encapsuler au sein de classes distinctes (les `Strategies`), et de les rendre interchangeables à l'exécution.
*   **Application :** Le système peut basculer dynamiquement entre un traitement AES ou RSA selon la configuration de l'entité, sans que le code appelant (le service principal) n'ait à gérer cette logique conditionnelle.

## 2. Chiffrement Symétrique (AES-256-GCM)
*   **Concept :** Algorithme utilisant la **même clé secrète** pour chiffrer et déchiffrer les données.
*   **Spécificité technique (GCM) :** *Galois/Counter Mode* est un mode de chiffrement authentifié (AEAD). Il assure la **confidentialité** (les données sont illisibles) mais aussi l'**intégrité** et l'**authenticité** via un *Tag d'authentification*. Si les données chiffrées sont manipulées illégitimement en base de données, le tag devient invalide et le déchiffrement échouera, empêchant les attaques par falsification.

## 3. Chiffrement Asymétrique (RSA-4096)
*   **Concept :** Utilise un système de bi-clés : une **clé publique** (diffusable, servant uniquement à chiffrer) et une **clé privée** (protégée, servant uniquement à déchiffrer).
*   **Limite technique :** Le protocole RSA est très gourmand en ressources (lent) et ne peut mathématiquement chiffrer que des quantités de données inférieures à la taille de sa propre clé. Il est proscrit pour le chiffrement direct de gros fichiers.

## 4. Chiffrement Hybride (Enveloppe Cryptographique)
*   **Concept :** Une technique de pointe qui fusionne les forces de l'AES (rapidité et traitement de données massives) et du RSA (distribution sécurisée des accès).
*   **Application (Pour les fichiers lourds médicaux) :** 
    1. Le fichier est chiffré rapidement avec une clé **AES éphémère** (Clé de Session générée à la volée).
    2. Cette clé AES est immédiatement chiffrée avec la **Clé Publique RSA** du destinataire.
    3. Les deux sont encapsulés ensemble (Payload AES + Clé AES chiffrée).
    4. Pour déchiffrer, la longue Clé Privée RSA déverrouille d'abord l'enveloppe contenant la clé AES, puis cette clé AES déchiffre instantanément le fichier final.

## 5. Transparence ORM via Doctrine Event Subscribers
*   **Concept :** Écouteurs (*Listeners/Subscribers*) qui se greffent automatiquement sur le cycle de vie des requêtes d'accès aux données (l'ORM Doctrine).
*   **Application :** Les hooks événementiels (`prePersist`, `preUpdate`) interceptent les attributs sensibles justes avant l'écriture en base, pour les chiffrer (*Encryption in Transit / At Rest*). Inversement, le hook `postLoad` les intercepte à la lecture pour les rendre en clair. Le développeur manipule donc ses objets "Patient" ou "Dossier" en clair dans le code PHP, sans jamais se soucier des appels cryptographiques.

## 6. Injection de Dépendances (Dependency Injection - DI)
*   **Concept :** Principe fondamental des architectures clean (SOLID). Les services réseaux ou configurations (comme les clés cryptographiques) sont fournis à l'objet plutôt que codés en dur ou récupérés de façon isolée.
*   **Application :** Le conteneur de services de Symfony injecte (via le constructeur) les clés brutes et les stratégies disponibles dans l'`EncryptionService`. Cela assure l'isolation des clés (souvent stockées en paramètre d'environnement `.env`), une sécurité accrue, et une testabilité unitaire parfaite (via des Mocks).
