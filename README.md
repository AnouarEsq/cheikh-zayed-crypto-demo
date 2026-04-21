# Portail Cryptographique - Fondation Cheikh Zaid (Démonstrateur)

Ce projet est le module de chiffrement de démonstration développé pour le système de gestion hospitalière de la Fondation Cheikh Zaid.
Il implémente une interface sécurisée permettant de tester le chiffrement de données textuelles et de documents médicaux lourds.

## 🔒 Fonctionnalités Principales implémentées

* **Chiffrement Symétrique (AES-256-GCM)** : Pour un traitement rapide des données avec garantie d'authenticité (AEAD).
* **Chiffrement Hybride (Enveloppe Cryptographique)** : Alliant la rapidité de l'AES-256 avec la sécurité de distribution des clés RSA-4096, un protocole idéal pour sécuriser les gros fichiers médicaux (imagerie, documents complets).
* **Architecture Flexible (Strategy Pattern)** : Le système permet l'injection dynamique des algorithmes requis via le conteneur de services (Dependency Injection) de Symfony.
* **Intégration ORM Transparente** : Exploitation théorique et technique des Events Subscribers de Doctrine pour réaliser un chiffrement transparent en base (Encryption at rest / in transit).

## 📄 Note d'Architecture

Veuillez vous référer au document détaillé : [Explication technique de l'Architecture](explication_architecture_chiffrement.md) présent à la racine du projet pour une analyse approfondie des concepts.

## 🛠️ Installation Locale (Pour Test ou Examen)

1. **Cloner le dépôt :**
   ```bash
   git clone [LIEN_DU_REPO]
   cd encryption-demo
   ```

2. **Installer les dépendances PHP :**
   ```bash
   composer install
   ```

3. **Environnement :**
   Copiez le fichier de template `.env.example` et renommez-le en `.env`. 
   *(Les clés privées/publiques RSA utiles pour la démo seront générées à la volée s'il n'y en a pas).*

4. **Lancer le serveur de démonstration :**
   ```bash
   php -S localhost:8000 test_server.php
   ```

5. **Accéder à l'interface :**
   Ouvrez le navigateur à l'adresse : [http://localhost:8000](http://localhost:8000)
