#!/bin/bash

# Script de déploiement avec cache-busting automatique
# Usage: ./scripts/deploy-with-cache-bust.sh [environment]

set -e

ENVIRONMENT=${1:-"staging"}
TIMESTAMP=$(date +%Y%m%d%H%M%S)

echo "🚀 Déploiement avec cache-busting automatique"
echo "🎯 Environnement: $ENVIRONMENT"
echo "🕒 Timestamp: $TIMESTAMP"

# 1. Cache-busting automatique
echo ""
echo "🔄 Étape 1: Cache-busting automatique..."
./scripts/cache-bust-safe.sh

# 2. Vérification des changements
echo ""
echo "📊 Étape 2: Vérification des changements..."
if git diff --quiet; then
    echo "ℹ️  Aucun changement détecté dans les assets"
else
    echo "✅ Cache-busting appliqué avec succès"
    echo "📋 Fichiers modifiés:"
    git diff --name-only
fi

# 3. Commit des changements si nécessaire
echo ""
echo "💾 Étape 3: Commit des changements..."
if ! git diff --quiet; then
    git add frontend/index.html
    git commit -m "chore: cache-busting automatique pour $ENVIRONMENT [skip ci]"
    echo "✅ Changements committés"
else
    echo "ℹ️  Aucun commit nécessaire"
fi

# 4. Push vers la branche appropriée
echo ""
echo "📤 Étape 4: Push vers la branche..."
BRANCH=""
case $ENVIRONMENT in
    "production"|"prod")
        BRANCH="main"
        ;;
    "staging"|"stage")
        BRANCH="staging"
        ;;
    *)
        BRANCH="develop"
        ;;
esac

echo "🌿 Branche cible: $BRANCH"

# Vérifier si on est sur la bonne branche
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    echo "⚠️  Vous êtes sur la branche '$CURRENT_BRANCH', déploiement vers '$BRANCH'"
    echo "🔀 Basculement vers $BRANCH..."
    git checkout $BRANCH
    git merge $CURRENT_BRANCH --no-edit
fi

# Push vers la branche cible
git push origin $BRANCH
echo "✅ Push vers $BRANCH réussi"

# 5. Déclenchement du déploiement
echo ""
echo "🚀 Étape 5: Déclenchement du déploiement..."
echo "📋 Le déploiement sera automatiquement déclenché par GitHub Actions"
echo "🔗 Vérifiez le statut sur: https://github.com/$(git remote get-url origin | sed 's/.*github.com[:/]\([^.]*\).*/\1/')/actions"

echo ""
echo "✅ Déploiement avec cache-busting terminé !"
echo "📊 Résumé:"
echo "   - Environnement: $ENVIRONMENT"
echo "   - Branche: $BRANCH"
echo "   - Timestamp: $TIMESTAMP"
echo "   - Cache-busting: ✅"
echo "   - Commit: ✅"
echo "   - Push: ✅"
