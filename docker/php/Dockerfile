FROM php:8.2-apache

# Activer mod_rewrite et autoriser .htaccess
RUN a2enmod rewrite && \
    sed -ri 's/AllowOverride\s+None/AllowOverride All/i' /etc/apache2/apache2.conf

# Quelques utilitaires + composer
RUN apt-get update && apt-get install -y \
    unzip git curl ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# Installer composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# On laisse le code monté par volume (pas de COPY ici)
