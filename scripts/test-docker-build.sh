#!/bin/bash

# Script pour tester le build Docker localement
set -e

echo "🐳 Test du build Docker local..."
echo ""

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Variables
IMAGE_NAME="receipt-parser-test"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "📁 Répertoire du projet : $PROJECT_ROOT"
echo ""

# Étape 1 : Nettoyer les images précédentes
echo "🧹 Nettoyage des anciennes images..."
docker rmi $IMAGE_NAME 2>/dev/null || true
echo ""

# Étape 2 : Build de l'image
echo "🔨 Build de l'image Docker..."
cd "$PROJECT_ROOT"

if docker build -t $IMAGE_NAME -f infra/Dockerfile .; then
    echo -e "${GREEN}✅ Build réussi !${NC}"
else
    echo -e "${RED}❌ Build échoué !${NC}"
    exit 1
fi
echo ""

# Étape 3 : Vérifier le contenu de l'image
echo "🔍 Vérification du contenu de l'image..."
echo ""

echo "Fichiers dans /var/www/html :"
docker run --rm $IMAGE_NAME ls -la /var/www/html/
echo ""

echo "Vérification de .htaccess :"
if docker run --rm $IMAGE_NAME test -f /var/www/html/.htaccess; then
    echo -e "${GREEN}✅ .htaccess présent${NC}"
    docker run --rm $IMAGE_NAME head -5 /var/www/html/.htaccess
else
    echo -e "${RED}❌ .htaccess manquant !${NC}"
fi
echo ""

echo "Vérification de index.php :"
if docker run --rm $IMAGE_NAME test -f /var/www/html/index.php; then
    echo -e "${GREEN}✅ index.php présent${NC}"
else
    echo -e "${RED}❌ index.php manquant !${NC}"
fi
echo ""

echo "Vérification du frontend :"
if docker run --rm $IMAGE_NAME test -d /var/www/html/frontend; then
    echo -e "${GREEN}✅ frontend/ présent${NC}"
    docker run --rm $IMAGE_NAME ls /var/www/html/frontend/
else
    echo -e "${RED}❌ frontend/ manquant !${NC}"
fi
echo ""

echo "Vérification de vendor/ (composer) :"
if docker run --rm $IMAGE_NAME test -d /var/www/html/vendor; then
    echo -e "${GREEN}✅ vendor/ installé${NC}"
    docker run --rm $IMAGE_NAME ls /var/www/html/vendor/ | head -10
else
    echo -e "${RED}❌ vendor/ manquant !${NC}"
fi
echo ""

# Étape 4 : Test de la configuration Apache
echo "🔧 Test de la configuration Apache..."
if docker run --rm $IMAGE_NAME apache2ctl configtest 2>&1 | grep -i "syntax ok"; then
    echo -e "${GREEN}✅ Configuration Apache OK${NC}"
else
    echo -e "${RED}❌ Erreur de configuration Apache${NC}"
    docker run --rm $IMAGE_NAME apache2ctl configtest
fi
echo ""

# Étape 5 : Lancer le conteneur en mode test
echo "🚀 Lancement du conteneur en mode test..."
echo ""

# Arrêter le conteneur s'il existe déjà
docker rm -f $IMAGE_NAME 2>/dev/null || true

# Lancer le conteneur
docker run -d \
    --name $IMAGE_NAME \
    -p 8080:8080 \
    -e PORT=8080 \
    -e APP_ENV=local \
    -e DEBUG=1 \
    $IMAGE_NAME

echo "⏳ Attente du démarrage du conteneur (10s)..."
sleep 10

# Vérifier les logs
echo ""
echo "📋 Logs du conteneur :"
echo "----------------------------------------"
docker logs $IMAGE_NAME
echo "----------------------------------------"
echo ""

# Étape 6 : Tests des endpoints
echo "🧪 Tests des endpoints..."
echo ""

test_endpoint() {
    local url=$1
    local description=$2
    
    echo -n "  Testing $description... "
    
    status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$url" 2>/dev/null || echo "000")
    
    if [ "$status_code" = "200" ]; then
        echo -e "${GREEN}✅ $status_code${NC}"
        return 0
    else
        echo -e "${RED}❌ $status_code${NC}"
        return 1
    fi
}

PASSED=0
FAILED=0

if test_endpoint "http://localhost:8080/" "Home page (/)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "http://localhost:8080/api/config" "Config endpoint (/api/config)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "http://localhost:8080/health" "Health endpoint (/health)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

if test_endpoint "http://localhost:8080/ready" "Ready endpoint (/ready)"; then
    ((PASSED++))
else
    ((FAILED++))
fi

echo ""
echo "📊 Résultat: $PASSED/4 tests réussis"
echo ""

# Étape 7 : Afficher les URLs pour tests manuels
echo "🌐 URLs pour tests manuels :"
echo "  - Home:   http://localhost:8080/"
echo "  - Config: http://localhost:8080/api/config"
echo "  - Health: http://localhost:8080/health"
echo "  - Ready:  http://localhost:8080/ready"
echo ""

# Étape 8 : Instructions de nettoyage
echo "🧹 Pour arrêter et nettoyer :"
echo "  docker rm -f $IMAGE_NAME"
echo "  docker rmi $IMAGE_NAME"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ Tous les tests sont passés ! L'image est prête pour le déploiement.${NC}"
    echo ""
    echo "Pour garder le conteneur en cours d'exécution et tester manuellement :"
    echo "  - Visitez http://localhost:8080 dans votre navigateur"
    echo "  - Arrêtez le conteneur avec : docker rm -f $IMAGE_NAME"
    exit 0
else
    echo -e "${RED}❌ $FAILED test(s) échoué(s). Vérifiez les logs ci-dessus.${NC}"
    echo ""
    echo "Pour déboguer :"
    echo "  - Voir les logs : docker logs $IMAGE_NAME"
    echo "  - Entrer dans le conteneur : docker exec -it $IMAGE_NAME bash"
    echo "  - Arrêter le conteneur : docker rm -f $IMAGE_NAME"
    exit 1
fi

