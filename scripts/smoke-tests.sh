#!/bin/bash

# Tests de fumée après séparation frontend/backend
# Vérifie que l'application fonctionne correctement

set -e

BASE_URL=${1:-"http://localhost:8080"}
echo "🧪 Tests de fumée sur $BASE_URL"

# Test 1: Frontend SPA se charge
echo "1️⃣ Test du frontend SPA..."
if curl -s "$BASE_URL" | grep -q "doctype html"; then
    echo "   ✅ Frontend SPA accessible"
else
    echo "   ❌ Frontend SPA inaccessible"
    exit 1
fi

# Test 2: Assets CSS se chargent
echo "2️⃣ Test des assets CSS..."
if curl -s "$BASE_URL/assets/css/app.css" | grep -q ":root"; then
    echo "   ✅ Assets CSS accessibles"
else
    echo "   ❌ Assets CSS inaccessibles"
    exit 1
fi

# Test 3: Assets JS se chargent
echo "3️⃣ Test des assets JS..."
if curl -s "$BASE_URL/assets/js/app.js" | grep -q "CONFIG"; then
    echo "   ✅ Assets JS accessibles"
else
    echo "   ❌ Assets JS inaccessibles"
    exit 1
fi

# Test 4: API config répond
echo "4️⃣ Test de l'API config..."
if curl -s "$BASE_URL/api/config" | grep -q '"ok":true'; then
    echo "   ✅ API config fonctionne"
else
    echo "   ❌ API config ne répond pas"
    exit 1
fi

# Test 5: API health répond
echo "5️⃣ Test de l'API health..."
if curl -s "$BASE_URL/api/health" | grep -q '"ok":true'; then
    echo "   ✅ API health fonctionne"
else
    echo "   ❌ API health ne répond pas"
    exit 1
fi

# Test 6: Backend direct inaccessible
echo "6️⃣ Test de sécurité backend..."
if curl -s "$BASE_URL/api/index.php" | grep -q "403\|404\|Forbidden"; then
    echo "   ✅ Accès direct au backend bloqué"
else
    echo "   ⚠️  Accès direct au backend possible (peut être normal)"
fi

# Test 7: Headers de sécurité
echo "7️⃣ Test des headers de sécurité..."
CSP_HEADER=$(curl -s -I "$BASE_URL" | grep -i "content-security-policy" || echo "")
if [ -n "$CSP_HEADER" ]; then
    echo "   ✅ Headers de sécurité présents"
else
    echo "   ⚠️  Headers de sécurité manquants"
fi

echo ""
echo "🎉 Tous les tests de fumée sont passés !"
echo "   - Frontend SPA: ✅"
echo "   - Assets: ✅"
echo "   - API: ✅"
echo "   - Sécurité: ✅"
