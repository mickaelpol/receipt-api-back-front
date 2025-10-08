#!/bin/bash

# Script pour tester le cloudbuild.yaml localement
# Usage: ./scripts/test-cloudbuild-locally.sh

set -e

echo "🧪 Test local du cloudbuild.yaml..."

# Vérifier que les fichiers requis existent
echo "🔍 Vérification des fichiers requis..."

REQUIRED_FILES=(
    "infra/Dockerfile"
    "backend/composer.json"
    "frontend/index.html"
    ".htaccess"
    "cloudbuild.yaml"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file (manquant)"
        exit 1
    fi
done

echo ""
echo "✅ Tous les fichiers requis sont présents !"
echo ""

# Tester la construction Docker localement
echo "🐳 Test de construction Docker locale..."

# Construire l'image Docker localement
docker build -f infra/Dockerfile -t receipt-api-test:local .

if [ $? -eq 0 ]; then
    echo "✅ Construction Docker réussie !"
else
    echo "❌ Erreur lors de la construction Docker"
    exit 1
fi

echo ""
echo "🧪 Test de l'application dans le conteneur..."

# Tester que l'application démarre
docker run --rm -d --name receipt-api-test -p 8081:8080 receipt-api-test:local

# Attendre que l'application démarre
sleep 5

# Tester les endpoints
echo "🔍 Test des endpoints..."

# Test de l'endpoint health
if curl -f http://localhost:8081/health > /dev/null 2>&1; then
    echo "✅ /health accessible"
else
    echo "❌ /health non accessible"
fi

# Test de l'endpoint ready
if curl -f http://localhost:8081/ready > /dev/null 2>&1; then
    echo "✅ /ready accessible"
else
    echo "❌ /ready non accessible"
fi

# Test de l'endpoint config
if curl -f http://localhost:8081/api/config > /dev/null 2>&1; then
    echo "✅ /api/config accessible"
else
    echo "❌ /api/config non accessible"
fi

# Nettoyer
docker stop receipt-api-test > /dev/null 2>&1
docker rmi receipt-api-test:local > /dev/null 2>&1

echo ""
echo "🎉 Test local du cloudbuild.yaml terminé avec succès !"
echo ""
echo "📋 Prochaines étapes :"
echo "   1. Vérifier les secrets GitHub : make check-secrets"
echo "   2. Configurer les secrets si nécessaire : make setup-secrets"
echo "   3. Pousser vers staging : git push origin staging"
echo "   4. Vérifier le déploiement sur GitHub Actions"
