#!/bin/bash

# CI guard pour détecter les violations CSP
# Vérifie que la CSP est stricte et qu'il n'y a pas de CDNs

set -e

echo "🔒 Vérification des violations CSP..."

VIOLATIONS=0

# Vérifier qu'il n'y a pas de références CDN dans le HTML
echo "1️⃣ Vérification des références CDN dans HTML..."
if grep -r "cdn\.jsdelivr\.net\|cdnjs\.cloudflare\.com\|unpkg\.com" frontend/ --include="*.html" --include="*.js" --include="*.css"; then
    echo "   ❌ Références CDN trouvées dans le code"
    echo "   → Remplacez par des assets locaux"
    VIOLATIONS=1
else
    echo "   ✅ Aucune référence CDN dans le code"
fi

# Vérifier que Bootstrap est local
echo "2️⃣ Vérification des assets Bootstrap locaux..."
if [ ! -f "frontend/assets/libs/bootstrap/5.3.3/bootstrap.min.css" ]; then
    echo "   ❌ Bootstrap CSS local manquant"
    VIOLATIONS=1
else
    echo "   ✅ Bootstrap CSS local présent"
fi

if [ ! -f "frontend/assets/libs/bootstrap/5.3.3/bootstrap.bundle.min.js" ]; then
    echo "   ❌ Bootstrap JS local manquant"
    VIOLATIONS=1
else
    echo "   ✅ Bootstrap JS local présent"
fi

# Vérifier la CSP dans .htaccess
echo "3️⃣ Vérification de la CSP dans .htaccess..."
if grep -q "cdn\.jsdelivr\.net" frontend/.htaccess; then
    echo "   ❌ CSP autorise encore cdn.jsdelivr.net"
    VIOLATIONS=1
else
    echo "   ✅ CSP exclut les CDNs"
fi

# Vérifier que Google est autorisé
if ! grep -q "accounts\.google\.com" frontend/.htaccess; then
    echo "   ❌ CSP n'autorise pas Google Identity"
    VIOLATIONS=1
else
    echo "   ✅ CSP autorise Google Identity"
fi

# Vérifier la structure des assets
echo "4️⃣ Vérification de la structure des assets..."
if [ ! -d "frontend/assets/libs" ]; then
    echo "   ❌ Dossier assets/libs manquant"
    VIOLATIONS=1
else
    echo "   ✅ Structure assets correcte"
fi

# Résultat
if [ $VIOLATIONS -eq 0 ]; then
    echo ""
    echo "✅ Aucune violation CSP détectée"
    echo "   - Assets locaux: ✅"
    echo "   - CDNs exclus: ✅"
    echo "   - Google autorisé: ✅"
    echo "   - Structure correcte: ✅"
    exit 0
else
    echo ""
    echo "❌ Violations CSP détectées !"
    echo "   → Corrigez les violations avant de continuer"
    exit 1
fi
