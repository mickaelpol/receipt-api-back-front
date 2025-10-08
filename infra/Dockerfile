# Dockerfile pour la production avec gestion des secrets
FROM php:8.1-apache

# Variables d'environnement
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV PHP_MEMORY_LIMIT=256M
ENV PHP_MAX_EXECUTION_TIME=30
ENV PHP_UPLOAD_MAX_FILESIZE=10M
ENV PHP_POST_MAX_SIZE=10M

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    build-essential \
    autoconf \
    && rm -rf /var/lib/apt/lists/*

# Installation des extensions PHP (regroupées pour éviter les erreurs)
RUN docker-php-ext-install zip curl mbstring xml pdo pdo_mysql opcache

# Configuration PHP pour la production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Configuration Apache
RUN a2enmod rewrite headers ssl
COPY infra/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY infra/apache/security.conf /etc/apache2/conf-available/security.conf

# Configuration du port 8080 pour Cloud Run
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf

# Configuration de sécurité Apache globale
RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf && \
    echo "ServerSignature Off" >> /etc/apache2/apache2.conf

# Copie des fichiers de l'application
COPY backend/ /var/www/html/
COPY frontend/ /var/www/html/frontend/
COPY .htaccess /var/www/html/.htaccess

# Création du fichier de credentials (sera remplacé par les vraies credentials en production)
RUN echo '{"type": "service_account", "project_id": "placeholder"}' > /tmp/credentials.json

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installation des dépendances PHP
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configuration des permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Création du répertoire pour les logs
RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs \
    && chmod -R 777 /var/www/html/logs

# Configuration de la sécurité
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_log = /var/log/php_errors.log" >> /usr/local/etc/php/conf.d/security.ini

# Configuration des headers de sécurité
RUN echo "Header always set X-Content-Type-Options nosniff" >> /etc/apache2/conf-available/security-headers.conf \
    && echo "Header always set X-Frame-Options DENY" >> /etc/apache2/conf-available/security-headers.conf \
    && echo "Header always set X-XSS-Protection \"1; mode=block\"" >> /etc/apache2/conf-available/security-headers.conf \
    && echo "Header always set Referrer-Policy \"strict-origin-when-cross-origin\"" >> /etc/apache2/conf-available/security-headers.conf \
    && echo "Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"" >> /etc/apache2/conf-available/security-headers.conf \
    && a2enconf security-headers

# Configuration de la CSP
RUN echo "Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com https://*.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' https://oauth2.googleapis.com https://openidconnect.googleapis.com https://accounts.googleapis.com https://www.googleapis.com; frame-src https://accounts.google.com https://apis.google.com https://content.googleapis.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';\"" >> /etc/apache2/conf-available/csp.conf \
    && a2enconf csp

# Script de démarrage
COPY infra/docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Exposition du port (Cloud Run utilise PORT=8080)
EXPOSE 8080

# Point d'entrée
ENTRYPOINT ["/usr/local/bin/start.sh"]
