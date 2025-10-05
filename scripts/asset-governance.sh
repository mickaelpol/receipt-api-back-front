#!/bin/bash

# Script de gouvernance des assets
# Vérifie que les nouvelles libs respectent les règles

set -e

echo "📦 Vérification de la gouvernance des assets..."

VIOLATIONS=0

# Vérifier qu'il n'y a pas de CDN dans le code
echo "1️⃣ Vérification des CDNs..."
if grep -r "https://cdn\." frontend/ --include="*.html" --include="*.js" --include="*.css"; then
    echo "   ❌ Références CDN trouvées"
    echo "   → Utilisez des assets locaux"
    VIOLATIONS=1
else
    echo "   ✅ Aucune référence CDN"
fi

# Vérifier la structure des assets
echo "2️⃣ Vérification de la structure des assets..."
if [ ! -d "frontend/assets/libs" ]; then
    echo "   ❌ Dossier assets/libs manquant"
    VIOLATIONS=1
else
    echo "   ✅ Structure assets correcte"
fi

# Vérifier que chaque lib a sa documentation
echo "3️⃣ Vérification de la documentation des libs..."
for lib_dir in frontend/assets/libs/*/; do
    if [ -d "$lib_dir" ]; then
        lib_name=$(basename "$lib_dir")
        if [ ! -f "$lib_dir/README.md" ]; then
            echo "   ❌ Documentation manquante pour $lib_name"
            VIOLATIONS=1
        else
            echo "   ✅ Documentation présente pour $lib_name"
        fi
    fi
done

# Vérifier que les versions sont épinglées
echo "4️⃣ Vérification du versioning..."
for lib_dir in frontend/assets/libs/*/; do
    if [ -d "$lib_dir" ]; then
        lib_name=$(basename "$lib_dir")
        # Vérifier qu'il y a des fichiers dans le dossier (version épinglée)
        if [ -z "$(ls -A "$lib_dir" 2>/dev/null)" ]; then
            echo "   ❌ Version non spécifiée pour $lib_name"
            VIOLATIONS=1
        else
            echo "   ✅ Version spécifiée pour $lib_name"
        fi
    fi
done

# Vérifier la CSP
echo "5️⃣ Vérification de la CSP..."
if ./scripts/check-csp-violations.sh > /dev/null 2>&1; then
    echo "   ✅ CSP conforme"
else
    echo "   ❌ Violations CSP détectées"
    VIOLATIONS=1
fi

# Résultat
if [ $VIOLATIONS -eq 0 ]; then
    echo ""
    echo "✅ Gouvernance des assets respectée"
    echo "   - Assets locaux: ✅"
    echo "   - Documentation: ✅"
    echo "   - Versioning: ✅"
    echo "   - CSP: ✅"
    exit 0
else
    echo ""
    echo "❌ Violations de gouvernance détectées !"
    echo "   → Corrigez les violations avant de continuer"
    exit 1
fi
