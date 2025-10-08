#!/bin/bash

# Script de démarrage pour Cloud Run (port dynamique)
# Nécessaire car Cloud Run injecte PORT=8080 mais Apache écoute sur 80 par défaut
set -e

# Configuration du port Apache (Cloud Run utilise PORT=8080)
PORT=${PORT:-8080}
echo "🚀 Starting Receipt API on port $PORT"

# Modifier la configuration Apache pour le port
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/Listen 8080/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/<VirtualHost \*:8080>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Vérification de la configuration Apache
apache2ctl configtest > /dev/null 2>&1 || {
    echo "❌ Apache configuration error"
    exit 1
}

echo "✅ Apache configured for port $PORT"
echo "✅ Routing: / → frontend, /api/* → backend"
echo "✅ Health: /health, /ready"

# Démarrage d'Apache
exec apache2-foreground
