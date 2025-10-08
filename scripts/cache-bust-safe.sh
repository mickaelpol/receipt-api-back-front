#!/bin/bash

# Script de cache-busting sécurisé - fonctionne avec Docker
# Usage: ./scripts/cache-bust-safe.sh

set -e

echo "🚀 Cache-busting sécurisé..."

# Générer un timestamp pour le cache-busting
TIMESTAMP=$(date +%Y%m%d%H%M%S)
echo "🕒 Timestamp généré: $TIMESTAMP"

# Créer un fichier temporaire
TEMP_FILE=$(mktemp)
echo "📝 Création du fichier temporaire: $TEMP_FILE"

# Copier le contenu et le modifier
cp frontend/index.html "$TEMP_FILE"

# Mettre à jour les références CSS
sed -i "s|assets/css/app\.css.*|assets/css/app.css?v=$TIMESTAMP\" rel=\"stylesheet\">|g" "$TEMP_FILE"

# Mettre à jour les références JS
sed -i "s|assets/js/app\.js.*|assets/js/app.js?v=$TIMESTAMP\"></script>|g" "$TEMP_FILE"

# Copier le fichier modifié vers le frontend
cp "$TEMP_FILE" frontend/index.html

# Nettoyer le fichier temporaire
rm "$TEMP_FILE"

echo "✅ Cache-busting terminé !"
echo ""
echo "📊 Résumé:"
echo "   - Timestamp: $TIMESTAMP"
echo "   - index.html mis à jour"
echo ""
echo "🎯 Avantages:"
echo "   - Simple et rapide"
echo "   - Compatible avec Docker"
echo "   - Cache-busting automatique"
