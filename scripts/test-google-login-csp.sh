#!/bin/bash

# Test spécifique pour le login Google avec CSP stricte
# Vérifie que le modal Google fonctionne sans erreurs CSP

set -e

BASE_URL=${1:-"http://localhost:8080"}
echo "🔐 Test du login Google avec CSP stricte sur $BASE_URL"

# Vérifier que l'application se charge
echo "1️⃣ Test de chargement de l'application..."
if curl -s "$BASE_URL" | grep -q "doctype html"; then
    echo "   ✅ Application accessible"
else
    echo "   ❌ Application inaccessible"
    exit 1
fi

# Vérifier que les scripts Google sont chargés
echo "2️⃣ Test des scripts Google..."
if curl -s "$BASE_URL" | grep -q "accounts.google.com/gsi/client"; then
    echo "   ✅ Script Google Identity chargé"
else
    echo "   ❌ Script Google Identity manquant"
    exit 1
fi

if curl -s "$BASE_URL" | grep -q "apis.google.com/js/api.js"; then
    echo "   ✅ Script Google APIs chargé"
else
    echo "   ❌ Script Google APIs manquant"
    exit 1
fi

# Vérifier la CSP pour Google
echo "3️⃣ Test de la CSP pour Google..."
CSP_HEADER=$(curl -s -I "$BASE_URL" | grep -i "content-security-policy" || echo "")

# Vérifier frame-src pour Google
if echo "$CSP_HEADER" | grep -q "frame-src.*accounts.google.com"; then
    echo "   ✅ frame-src autorise accounts.google.com"
else
    echo "   ❌ frame-src n'autorise pas accounts.google.com"
    exit 1
fi

# Vérifier script-src pour Google
if echo "$CSP_HEADER" | grep -q "script-src.*accounts.google.com"; then
    echo "   ✅ script-src autorise accounts.google.com"
else
    echo "   ❌ script-src n'autorise pas accounts.google.com"
    exit 1
fi

# Vérifier connect-src pour Google
if echo "$CSP_HEADER" | grep -q "connect-src.*oauth2.googleapis.com"; then
    echo "   ✅ connect-src autorise oauth2.googleapis.com"
else
    echo "   ❌ connect-src n'autorise pas oauth2.googleapis.com"
    exit 1
fi

# Vérifier que les CDNs sont exclus
echo "4️⃣ Test d'exclusion des CDNs..."
if echo "$CSP_HEADER" | grep -q "cdn.jsdelivr.net"; then
    echo "   ❌ CSP autorise encore cdn.jsdelivr.net"
    exit 1
else
    echo "   ✅ CSP exclut les CDNs"
fi

# Test de l'API config
echo "5️⃣ Test de l'API config..."
if curl -s "$BASE_URL/api/config" | grep -q '"ok":true'; then
    echo "   ✅ API config fonctionne"
else
    echo "   ❌ API config ne répond pas"
    exit 1
fi

# Test de l'API auth/me (sans token, doit retourner 401)
echo "6️⃣ Test de l'API auth/me..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/auth/me")
if [ "$HTTP_STATUS" -eq 401 ]; then
    echo "   ✅ API auth/me retourne 401 (attendu sans token)"
else
    echo "   ❌ API auth/me retourne $HTTP_STATUS (attendu 401)"
    exit 1
fi

echo ""
echo "🎉 Tous les tests Google login CSP sont passés !"
echo "   - Application accessible: ✅"
echo "   - Scripts Google chargés: ✅"
echo "   - CSP autorise Google: ✅"
echo "   - CDNs exclus: ✅"
echo "   - APIs fonctionnelles: ✅"
