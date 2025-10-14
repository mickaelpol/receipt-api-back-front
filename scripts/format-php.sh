#!/bin/bash

# Script pour formater le code PHP avec PHPCBF via Docker
# Contourne le problème des volumes read-only

set -e

echo "🔧 Formatage PHP avec PHPCBF..."

# Vérifier que Docker est démarré
if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up" 2>/dev/null; then
    echo "❌ Le conteneur Docker n'est pas démarré"
    echo "💡 Lancez: make up"
    exit 1
fi

# Créer un répertoire temporaire dans le conteneur
CONTAINER_TMP="/tmp/phpcbf-format-$$"
docker compose -f infra/docker-compose.yml -p receipt exec app mkdir -p "$CONTAINER_TMP"

# Copier les fichiers backend dans le conteneur
echo "  → Copie des fichiers backend..."
docker compose -f infra/docker-compose.yml -p receipt exec app sh -c "cp -r /var/www/html/*.php $CONTAINER_TMP/ 2>/dev/null || true"
docker compose -f infra/docker-compose.yml -p receipt exec app sh -c "cp /var/www/html/phpcs.xml $CONTAINER_TMP/ 2>/dev/null || true"
docker compose -f infra/docker-compose.yml -p receipt exec app sh -c "mkdir -p $CONTAINER_TMP/vendor && cp -r /var/www/html/vendor $CONTAINER_TMP/"

# Exécuter PHPCBF dans le répertoire temporaire
echo "  → Exécution de PHPCBF (standard PSR12)..."
docker compose -f infra/docker-compose.yml -p receipt exec app sh -c "cd $CONTAINER_TMP && php -d memory_limit=512M vendor/bin/phpcbf --standard=PSR12 *.php 2>&1" || echo "  ✓ Formatage terminé"

# Copier les fichiers formatés vers l'hôte
echo "  → Récupération des fichiers formatés..."
for file in backend/*.php; do
    filename=$(basename "$file")
    if [ "$filename" != "composer.json" ] && [ "$filename" != "composer.lock" ]; then
        docker compose -f infra/docker-compose.yml -p receipt exec app cat "$CONTAINER_TMP/$filename" > "$file" 2>/dev/null || true
    fi
done

# Nettoyer
docker compose -f infra/docker-compose.yml -p receipt exec app rm -rf "$CONTAINER_TMP"

echo "✅ Formatage PHP terminé!"
