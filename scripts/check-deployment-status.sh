#!/bin/bash

# Script pour vérifier l'état du déploiement Cloud Run
set -e

# Configuration
PROJECT_ID="scan-document-ai"
SERVICE_NAME="receipt-parser"
REGION="europe-west9"

echo "🔍 Vérification du statut du déploiement..."
echo ""

# Vérifier si gcloud est installé
if ! command -v gcloud &> /dev/null; then
    echo "❌ gcloud n'est pas installé. Veuillez l'installer : https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Obtenir l'URL du service
echo "📡 Récupération de l'URL du service..."
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME \
    --region=$REGION \
    --project=$PROJECT_ID \
    --format='value(status.url)' 2>/dev/null)

if [ -z "$SERVICE_URL" ]; then
    echo "❌ Service non trouvé ou non déployé"
    exit 1
fi

echo "✅ Service URL: $SERVICE_URL"
echo ""

# Tester les endpoints
echo "🧪 Test des endpoints..."
echo ""

test_endpoint() {
    local url=$1
    local description=$2
    
    echo -n "  Testing $description... "
    
    status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")
    
    if [ "$status_code" = "200" ]; then
        echo "✅ $status_code"
        return 0
    else
        echo "❌ $status_code"
        return 1
    fi
}

# Tests
PASSED=0
FAILED=0

if test_endpoint "$SERVICE_URL/" "Home page (/)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "$SERVICE_URL/api/config" "Config endpoint (/api/config)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "$SERVICE_URL/health" "Health endpoint (/health)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "$SERVICE_URL/ready" "Ready endpoint (/ready)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

echo ""
echo "📊 Résultat: $PASSED/4 tests réussis"

if [ $FAILED -eq 0 ]; then
    echo "✅ Tous les tests sont passés ! Le déploiement est réussi."
    exit 0
else
    echo "❌ $FAILED test(s) échoué(s). Vérifiez les logs:"
    echo ""
    echo "   Logs Cloud Run:"
    echo "   gcloud logging read \"resource.type=cloud_run_revision\" --project=$PROJECT_ID --limit=50"
    echo ""
    echo "   Ou visitez:"
    echo "   https://console.cloud.google.com/run/detail/$REGION/$SERVICE_NAME/logs?project=$PROJECT_ID"
    exit 1
fi

