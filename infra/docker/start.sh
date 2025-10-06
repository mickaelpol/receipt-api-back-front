#!/bin/bash

# Script de démarrage pour l'application Receipt API

set -e

echo "=== Démarrage de l'application Receipt API ==="

# Vérification des permissions (skip readonly mounted files)
echo "Vérification des permissions..."
chown -R www-data:www-data /var/www/html 2>/dev/null || true
chmod -R 755 /var/www/html 2>/dev/null || true

# Création des répertoires nécessaires
echo "Création des répertoires nécessaires..."
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/tmp
mkdir -p /var/www/html/cache

# Configuration des permissions pour les répertoires
chown -R www-data:www-data /var/www/html/logs
chown -R www-data:www-data /var/www/html/tmp
chown -R www-data:www-data /var/www/html/cache

chmod -R 777 /var/www/html/logs
chmod -R 777 /var/www/html/tmp
chmod -R 777 /var/www/html/cache

# Vérification de la configuration Apache
echo "Vérification de la configuration Apache..."
apache2ctl configtest

# Démarrage d'Apache
echo "Démarrage d'Apache..."
exec apache2-foreground
