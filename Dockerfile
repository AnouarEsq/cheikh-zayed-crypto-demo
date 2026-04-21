FROM php:8.2-cli

# Installation des dépendances système nécessaires pour Composer et Symfony
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    && docker-php-ext-install zip

# Copie de Composer depuis l'image officielle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définition du répertoire de travail
WORKDIR /app

# Copie de tous les fichiers du projet
COPY . .

# Installation des dépendances avec Composer
RUN composer install --no-dev --optimize-autoloader

# Démarrage du serveur PHP sur le port fourni par Render
CMD php -S 0.0.0.0:${PORT:-10000} test_server.php
