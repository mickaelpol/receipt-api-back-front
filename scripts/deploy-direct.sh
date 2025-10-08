#!/bin/bash

# Script de déploiement direct vers Cloud Run (sans passer par GitHub Actions)
# Utilise directement gcloud builds submit

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ID="scan-document-ai"
REGION="europe-west9"
SERVICE_NAME="receipt-parser"
REPO_NAME="apps-ticket"

echo -e "${BLUE}🚀 Déploiement direct vers Cloud Run${NC}"
echo ""

# Vérifier que gcloud est installé
if ! command -v gcloud &> /dev/null; then
    echo -e "${RED}❌ gcloud n'est pas installé${NC}"
    echo "Installez-le depuis: https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Vérifier l'authentification
echo -e "${YELLOW}🔐 Vérification de l'authentification...${NC}"
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q .; then
    echo -e "${RED}❌ Pas de compte authentifié${NC}"
    echo "Connectez-vous avec: gcloud auth login"
    exit 1
fi

ACCOUNT=$(gcloud auth list --filter=status:ACTIVE --format="value(account)" | head -1)
echo -e "${GREEN}✅ Connecté en tant que: $ACCOUNT${NC}"
echo ""

# Vérifier le projet actif
CURRENT_PROJECT=$(gcloud config get-value project 2>/dev/null)
if [ "$CURRENT_PROJECT" != "$PROJECT_ID" ]; then
    echo -e "${YELLOW}⚠️  Projet actuel: $CURRENT_PROJECT${NC}"
    echo -e "${YELLOW}   Changement vers: $PROJECT_ID${NC}"
    gcloud config set project $PROJECT_ID
fi

# Cache-busting
echo -e "${BLUE}🔄 Application du cache-busting...${NC}"
chmod +x scripts/cache-bust-safe.sh
./scripts/cache-bust-safe.sh

if git diff --quiet frontend/index.html; then
    echo -e "${GREEN}✅ Pas de changement dans les assets${NC}"
else
    echo -e "${GREEN}✅ Cache-busting appliqué${NC}"
    git add frontend/index.html
    git commit -m "chore: cache-busting automatique [skip ci]" || true
fi
echo ""

# Obtenir le SHA actuel
GIT_SHA=$(git rev-parse --short HEAD)
echo -e "${BLUE}📝 Version: $GIT_SHA${NC}"
echo ""

# Confirmation
echo -e "${YELLOW}⚠️  Vous allez déployer vers:${NC}"
echo -e "   Projet: ${GREEN}$PROJECT_ID${NC}"
echo -e "   Service: ${GREEN}$SERVICE_NAME${NC}"
echo -e "   Région: ${GREEN}$REGION${NC}"
echo -e "   Version: ${GREEN}$GIT_SHA${NC}"
echo ""
read -p "Continuer ? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ Déploiement annulé${NC}"
    exit 0
fi
echo ""

# Déploiement via Cloud Build
echo -e "${BLUE}🔨 Lancement du build et déploiement...${NC}"
echo ""

gcloud builds submit \
    --config=cloudbuild.yaml \
    --project=$PROJECT_ID \
    --substitutions=_REGION=$REGION,_SERVICE_NAME=$SERVICE_NAME,SHORT_SHA=$GIT_SHA,COMMIT_SHA=$(git rev-parse HEAD),BRANCH_NAME=$(git rev-parse --abbrev-ref HEAD),_ENV=production

echo ""
echo -e "${GREEN}✅ Déploiement terminé !${NC}"
echo ""

# Obtenir l'URL du service
SERVICE_URL=$(gcloud run services describe $SERVICE_NAME \
    --region=$REGION \
    --project=$PROJECT_ID \
    --format='value(status.url)' 2>/dev/null)

if [ -n "$SERVICE_URL" ]; then
    echo -e "${GREEN}🌐 URL du service:${NC}"
    echo -e "   $SERVICE_URL"
    echo ""
    
    # Tests rapides
    echo -e "${BLUE}🧪 Tests rapides...${NC}"
    
    # Test home
    if curl -f -s "$SERVICE_URL/" > /dev/null; then
        echo -e "   ${GREEN}✅${NC} Home page"
    else
        echo -e "   ${RED}❌${NC} Home page"
    fi
    
    # Test config
    if curl -f -s "$SERVICE_URL/api/config" > /dev/null; then
        echo -e "   ${GREEN}✅${NC} /api/config"
    else
        echo -e "   ${RED}❌${NC} /api/config"
    fi
    
    # Test health
    if curl -f -s "$SERVICE_URL/health" > /dev/null; then
        echo -e "   ${GREEN}✅${NC} /health"
    else
        echo -e "   ${RED}❌${NC} /health"
    fi
    
    # Test ready
    if curl -f -s "$SERVICE_URL/ready" > /dev/null; then
        echo -e "   ${GREEN}✅${NC} /ready"
    else
        echo -e "   ${RED}❌${NC} /ready"
    fi
    
    echo ""
fi

echo -e "${GREEN}🎉 Déploiement réussi !${NC}"
echo ""
echo "📋 Commandes utiles:"
echo "   Logs:   gcloud logging tail \"resource.type=cloud_run_revision\" --project=$PROJECT_ID"
echo "   Status: gcloud run services describe $SERVICE_NAME --region=$REGION --project=$PROJECT_ID"

