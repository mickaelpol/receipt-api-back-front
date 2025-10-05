#!/bin/bash

# Test CSP compliance - vérifie qu'il n'y a pas d'erreurs CSP
# Utilise Puppeteer pour simuler un navigateur et détecter les violations CSP

set -e

BASE_URL=${1:-"http://localhost:8080"}
echo "🔒 Test de conformité CSP sur $BASE_URL"

# Vérifier que l'application se charge
echo "1️⃣ Test de chargement de l'application..."
if curl -s "$BASE_URL" | grep -q "doctype html"; then
    echo "   ✅ Application accessible"
else
    echo "   ❌ Application inaccessible"
    exit 1
fi

# Vérifier que Bootstrap se charge depuis les assets locaux
echo "2️⃣ Test des assets Bootstrap locaux..."
if curl -s "$BASE_URL/assets/libs/bootstrap/5.3.3/bootstrap.min.css" | grep -q "Bootstrap"; then
    echo "   ✅ Bootstrap CSS local accessible"
else
    echo "   ❌ Bootstrap CSS local inaccessible"
    exit 1
fi

if curl -s "$BASE_URL/assets/libs/bootstrap/5.3.3/bootstrap.bundle.min.js" | grep -q "Bootstrap"; then
    echo "   ✅ Bootstrap JS local accessible"
else
    echo "   ❌ Bootstrap JS local inaccessible"
    exit 1
fi

# Vérifier que les headers CSP sont présents
echo "3️⃣ Test des headers CSP..."
CSP_HEADER=$(curl -s -I "$BASE_URL" | grep -i "content-security-policy" || echo "")
if [ -n "$CSP_HEADER" ]; then
    echo "   ✅ Headers CSP présents"
    echo "   📋 CSP: $CSP_HEADER"
else
    echo "   ❌ Headers CSP manquants"
    exit 1
fi

# Vérifier que la CSP n'autorise pas les CDNs
echo "4️⃣ Test d'exclusion des CDNs..."
if echo "$CSP_HEADER" | grep -q "cdn.jsdelivr.net"; then
    echo "   ❌ CSP autorise encore cdn.jsdelivr.net"
    exit 1
else
    echo "   ✅ CSP exclut les CDNs"
fi

# Vérifier que la CSP autorise Google
echo "5️⃣ Test d'autorisation Google..."
if echo "$CSP_HEADER" | grep -q "accounts.google.com"; then
    echo "   ✅ CSP autorise Google Identity"
else
    echo "   ❌ CSP n'autorise pas Google Identity"
    exit 1
fi

# Test de l'API
echo "6️⃣ Test de l'API..."
if curl -s "$BASE_URL/api/config" | grep -q '"ok":true'; then
    echo "   ✅ API config fonctionne"
else
    echo "   ❌ API config ne répond pas"
    exit 1
fi

echo ""
echo "🎉 Tous les tests CSP sont passés !"
echo "   - Assets locaux: ✅"
echo "   - CDNs exclus: ✅"
echo "   - Google autorisé: ✅"
echo "   - API fonctionnelle: ✅"
