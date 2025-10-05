#!/bin/bash

# Politique des source maps pour dev vs prod
# Vérifie que les source maps respectent la politique

set -e

echo "🗺️  Vérification de la politique des source maps..."

VIOLATIONS=0

# Vérifier qu'il n'y a pas de source maps externes
echo "1️⃣ Vérification des source maps externes..."
if find frontend/ -name "*.js" -o -name "*.css" | xargs grep -l "//# sourceMappingURL=http" 2>/dev/null; then
    echo "   ❌ Source maps externes trouvées"
    echo "   → Utilisez des source maps locales ou désactivez-les"
    VIOLATIONS=1
else
    echo "   ✅ Aucune source map externe"
fi

# Vérifier que les source maps locales sont présentes si nécessaires
echo "2️⃣ Vérification des source maps locales..."
if [ -f "frontend/assets/libs/bootstrap/5.3.3/bootstrap.min.css.map" ]; then
    echo "   ✅ Source map CSS locale présente"
else
    echo "   ℹ️  Source map CSS locale non présente (normal en prod)"
fi

if [ -f "frontend/assets/libs/bootstrap/5.3.3/bootstrap.bundle.min.js.map" ]; then
    echo "   ✅ Source map JS locale présente"
else
    echo "   ℹ️  Source map JS locale non présente (normal en prod)"
fi

# Vérifier la configuration de production
echo "3️⃣ Vérification de la configuration de production..."
if [ -f "frontend/.htaccess" ]; then
    if grep -q "sourceMappingURL" frontend/.htaccess; then
        echo "   ⚠️  Source maps configurées dans .htaccess"
    else
        echo "   ✅ Pas de source maps dans .htaccess (normal en prod)"
    fi
else
    echo "   ❌ Fichier .htaccess manquant"
    VIOLATIONS=1
fi

# Vérifier que les assets locaux n'ont pas de références externes
echo "4️⃣ Vérification des références externes dans les assets..."
if find frontend/assets/libs/ -name "*.css" -o -name "*.js" | xargs grep -l "http" 2>/dev/null; then
    echo "   ❌ Références HTTP trouvées dans les assets locaux"
    echo "   → Vérifiez que les assets sont complètement autonomes"
    VIOLATIONS=1
else
    echo "   ✅ Assets locaux autonomes"
fi

# Résultat
if [ $VIOLATIONS -eq 0 ]; then
    echo ""
    echo "✅ Politique des source maps respectée"
    echo "   - Pas de source maps externes: ✅"
    echo "   - Assets locaux autonomes: ✅"
    echo "   - Configuration production: ✅"
    exit 0
else
    echo ""
    echo "❌ Violations de la politique des source maps !"
    echo "   → Corrigez les violations avant de continuer"
    exit 1
fi
