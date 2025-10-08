#!/bin/bash

# Script d'installation des Git hooks

set -e

echo "🔧 Installation des Git hooks..."
echo ""

# Couleurs
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

# Créer le répertoire .git/hooks s'il n'existe pas
if [ ! -d ".git/hooks" ]; then
    echo -e "${BLUE}Création du répertoire .git/hooks...${NC}"
    mkdir -p .git/hooks
fi

# Installer pre-commit
if [ -f ".githooks/pre-commit" ]; then
    echo -e "${BLUE}Installation de pre-commit...${NC}"
    cp .githooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
    echo -e "${GREEN}✅ pre-commit installé${NC}"
else
    echo "❌ .githooks/pre-commit non trouvé"
    exit 1
fi

# Installer pre-push
if [ -f ".githooks/pre-push" ]; then
    echo -e "${BLUE}Installation de pre-push...${NC}"
    cp .githooks/pre-push .git/hooks/pre-push
    chmod +x .git/hooks/pre-push
    echo -e "${GREEN}✅ pre-push installé${NC}"
else
    echo "❌ .githooks/pre-push non trouvé"
    exit 1
fi

echo ""
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ✅ Git hooks installés avec succès   ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""
echo "Les hooks vont maintenant:"
echo "  • Vérifier votre code avant chaque commit"
echo "  • Vous demander confirmation avant push vers main"
echo "  • Empêcher le commit de secrets"
echo ""
echo "Pour bypasser (déconseillé):"
echo "  git commit --no-verify"
echo "  git push --no-verify"
echo ""

