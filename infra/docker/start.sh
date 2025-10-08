#!/bin/bash

# Script de démarrage pour Cloud Run (port dynamique)
set -e

# Configuration du port Apache (Cloud Run utilise PORT=8080)
PORT=${PORT:-8080}
echo "🚀 Starting Receipt API on port $PORT"

# Vérifier que les fichiers critiques existent
echo "🔍 Checking critical files..."
if [ ! -f "/var/www/html/.htaccess" ]; then
    echo "❌ .htaccess not found!"
    exit 1
fi
if [ ! -f "/var/www/html/index.php" ]; then
    echo "❌ index.php not found!"
    exit 1
fi
if [ ! -d "/var/www/html/frontend" ]; then
    echo "❌ frontend directory not found!"
    exit 1
fi
echo "✅ Critical files found"

# Configurer le port dans Apache
echo "🔧 Configuring Apache for port $PORT..."

# Backup et modification de ports.conf
cp /etc/apache2/ports.conf /etc/apache2/ports.conf.bak
echo "Listen $PORT" > /etc/apache2/ports.conf

# Backup et modification du VirtualHost
cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/000-default.conf.bak
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Vérification de la configuration Apache
echo "🔍 Testing Apache configuration..."
if apache2ctl configtest 2>&1 | grep -i "syntax ok"; then
    echo "✅ Apache configuration OK"
else
    echo "❌ Apache configuration error:"
    apache2ctl configtest
    exit 1
fi

echo "✅ Apache configured for port $PORT"
echo "✅ Routing: / → frontend, /api/* → backend"
echo "✅ Health: /health, /ready"
echo "🚀 Starting Apache..."

# Démarrage d'Apache en foreground
exec apache2-foreground
