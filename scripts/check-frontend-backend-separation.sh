#!/bin/bash

# Script CI pour vérifier la séparation frontend/backend
# Empêche les fichiers frontend dans backend/

set -e

echo "🔍 Vérification de la séparation frontend/backend..."

# Vérifier qu'il n'y a pas de fichiers frontend dans backend/
FRONTEND_FILES_IN_BACKEND=0

# Vérifier index.html dans backend/
if [ -f "backend/index.html" ]; then
    echo "❌ ERREUR: backend/index.html ne doit pas exister"
    echo "   → Déplacez le fichier vers frontend/index.html"
    FRONTEND_FILES_IN_BACKEND=1
fi

# Vérifier assets/ dans backend/
if [ -d "backend/assets" ]; then
    echo "❌ ERREUR: backend/assets/ ne doit pas exister"
    echo "   → Déplacez les assets vers frontend/assets/"
    FRONTEND_FILES_IN_BACKEND=1
fi

# Vérifier les fichiers CSS/JS dans backend/
if find backend/ -name "*.css" -o -name "*.js" | grep -q .; then
    echo "❌ ERREUR: Fichiers CSS/JS trouvés dans backend/"
    echo "   → Déplacez les fichiers vers frontend/assets/"
    find backend/ -name "*.css" -o -name "*.js" | while read file; do
        echo "     - $file"
    done
    FRONTEND_FILES_IN_BACKEND=1
fi

# Vérifier que frontend/ contient les fichiers requis
if [ ! -f "frontend/index.html" ]; then
    echo "❌ ERREUR: frontend/index.html manquant"
    FRONTEND_FILES_IN_BACKEND=1
fi

if [ ! -d "frontend/assets" ]; then
    echo "❌ ERREUR: frontend/assets/ manquant"
    FRONTEND_FILES_IN_BACKEND=1
fi

# Résultat
if [ $FRONTEND_FILES_IN_BACKEND -eq 0 ]; then
    echo "✅ Séparation frontend/backend correcte"
    echo "   - Frontend: frontend/"
    echo "   - Backend: backend/ (API uniquement)"
    exit 0
else
    echo ""
    echo "📋 RÈGLES DE SÉPARATION:"
    echo "   - Frontend: frontend/ (HTML, CSS, JS, assets)"
    echo "   - Backend: backend/ (PHP, API uniquement)"
    echo "   - Infrastructure: infra/ (Docker, CI/CD)"
    exit 1
fi
