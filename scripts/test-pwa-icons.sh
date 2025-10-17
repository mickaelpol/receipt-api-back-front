#!/bin/bash

# Script de diagnostic PWA - Icônes et Splash Screens
# Test tous les éléments nécessaires pour une PWA fonctionnelle

set -e

URL="${1:-http://localhost:8080}"

echo "════════════════════════════════════════════════════════"
echo "🔍 Diagnostic PWA - Scan2Sheet"
echo "════════════════════════════════════════════════════════"
echo ""
echo "URL testée : $URL"
echo ""

# Fonction pour tester une URL
test_url() {
    local url=$1
    local description=$2

    status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)

    if [ "$status" = "200" ]; then
        echo "✅ $description"
        return 0
    else
        echo "❌ $description (HTTP $status)"
        return 1
    fi
}

echo "📱 Test des icônes"
echo "─────────────────────────────────────────────────────────"
test_url "$URL/assets/icons/favicon.ico" "Favicon.ico"
test_url "$URL/assets/icons/icon-192x192.png" "Icône 192x192 PNG"
test_url "$URL/assets/icons/icon-512x512.png" "Icône 512x512 PNG"
test_url "$URL/assets/icons/icon-1024x1024.png" "Icône 1024x1024 PNG"
echo ""

echo "📄 Test du manifest.json"
echo "─────────────────────────────────────────────────────────"
test_url "$URL/manifest.json" "Manifest.json"

# Vérifier le contenu du manifest
echo ""
echo "🔎 Contenu du manifest :"
manifest=$(curl -s "$URL/manifest.json")
echo "$manifest" | jq -r '"  background_color: " + .background_color'
echo "$manifest" | jq -r '"  theme_color: " + .theme_color'
echo "$manifest" | jq -r '"  Nombre d'\''icônes: " + (.icons | length | tostring)'

# Vérifier les icônes maskables
maskable_count=$(echo "$manifest" | jq '[.icons[] | select(.purpose | contains("maskable"))] | length')
echo "  Icônes maskables: $maskable_count"

if [ "$maskable_count" -gt "0" ]; then
    echo "  ✅ Support maskable Android activé"
else
    echo "  ⚠️  Aucune icône maskable trouvée"
fi

echo ""
echo "🌅 Test des splash screens iOS"
echo "─────────────────────────────────────────────────────────"
splash_screens=(
    "splash-1125x2436.png"
    "splash-1170x2532.png"
    "splash-1179x2556.png"
    "splash-1242x2688.png"
    "splash-1284x2778.png"
    "splash-1290x2796.png"
    "splash-1536x2048.png"
    "splash-1668x2388.png"
    "splash-2048x2732.png"
)

splash_ok=0
splash_total=${#splash_screens[@]}

for splash in "${splash_screens[@]}"; do
    if test_url "$URL/assets/splash/$splash" "$splash" >/dev/null 2>&1; then
        ((splash_ok++))
    fi
done

echo "Splash screens : $splash_ok/$splash_total disponibles"
echo ""

echo "🔧 Test du service worker"
echo "─────────────────────────────────────────────────────────"
test_url "$URL/service-worker.js" "Service Worker"

# Extraire la version
sw_version=$(curl -s "$URL/service-worker.js" | grep "CACHE_VERSION =" | head -1 | sed -n "s/.*'\(v[^']*\)'.*/\1/p")
echo "Version du SW : $sw_version"
echo ""

echo "🏠 Test de la page principale"
echo "─────────────────────────────────────────────────────────"
test_url "$URL/" "Page d'accueil"
test_url "$URL/api/config" "API Config"
test_url "$URL/api/health" "API Health"
echo ""

echo "════════════════════════════════════════════════════════"
echo "📊 Résumé"
echo "════════════════════════════════════════════════════════"
echo ""

# Calculer le score
total_tests=17
passed_tests=0

# Retest pour compter
test_url "$URL/assets/icons/favicon.ico" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/assets/icons/icon-192x192.png" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/assets/icons/icon-512x512.png" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/assets/icons/icon-1024x1024.png" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/manifest.json" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/service-worker.js" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/api/config" "" >/dev/null 2>&1 && ((passed_tests++)) || true
test_url "$URL/api/health" "" >/dev/null 2>&1 && ((passed_tests++)) || true

passed_tests=$((passed_tests + splash_ok))

percentage=$((passed_tests * 100 / total_tests))

echo "Tests réussis : $passed_tests/$total_tests ($percentage%)"
echo ""

if [ "$percentage" -ge 90 ]; then
    echo "✅ Excellent ! Votre PWA est prête."
elif [ "$percentage" -ge 70 ]; then
    echo "⚠️  Bien mais améliorable. Vérifiez les tests échoués."
else
    echo "❌ Problèmes détectés. Corrigez les erreurs ci-dessus."
fi

echo ""
echo "════════════════════════════════════════════════════════"
echo ""

exit 0
