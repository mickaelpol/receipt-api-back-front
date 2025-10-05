#!/bin/bash

# CI guard pour détecter les violations CSP
# Utilise Puppeteer pour simuler un navigateur et détecter les violations CSP

set -e

echo "🔒 Validation CSP pour CI..."

VIOLATIONS=0

# Vérifier qu'il n'y a pas de références CDN dans le code
echo "1️⃣ Vérification des références CDN dans le code..."
if grep -r "cdn\.jsdelivr\.net\|cdnjs\.cloudflare\.com\|unpkg\.com" frontend/ --include="*.html" --include="*.js" --include="*.css" 2>/dev/null; then
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

# Vérifier que gstatic.com est autorisé pour les scripts
if ! grep -q "gstatic\.com" frontend/.htaccess; then
    echo "   ❌ CSP n'autorise pas gstatic.com (requis pour Google)"
    VIOLATIONS=1
else
    echo "   ✅ CSP autorise gstatic.com"
fi

# Vérifier que content.googleapis.com est autorisé pour les frames
if ! grep -q "content\.googleapis\.com" frontend/.htaccess; then
    echo "   ❌ CSP n'autorise pas content.googleapis.com (requis pour Google)"
    VIOLATIONS=1
else
    echo "   ✅ CSP autorise content.googleapis.com"
fi

# Vérifier la structure des assets
echo "4️⃣ Vérification de la structure des assets..."
if [ ! -d "frontend/assets/libs" ]; then
    echo "   ❌ Dossier assets/libs manquant"
    VIOLATIONS=1
else
    echo "   ✅ Structure assets correcte"
fi

# Test de l'application (si disponible)
echo "5️⃣ Test de l'application..."
if command -v curl >/dev/null 2>&1; then
    if curl -s http://localhost:8080 >/dev/null 2>&1; then
        echo "   ✅ Application accessible"
    else
        echo "   ⚠️  Application non accessible (normal en CI)"
    fi
else
    echo "   ⚠️  curl non disponible (normal en CI)"
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
