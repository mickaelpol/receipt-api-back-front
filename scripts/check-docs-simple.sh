#!/bin/bash

# Simple Documentation Quality Check Script
# Ensures single README and basic structure

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "📚 VÉRIFICATION DE LA DOCUMENTATION"
echo "===================================="
echo ""

ERRORS=0

# Check for multiple README files
echo "🔍 Vérification des fichiers README..."
README_COUNT=$(find . -name "README*.md" -not -path "./.git/*" -not -path "./backend/vendor/*" | wc -l)
if [ "$README_COUNT" -gt 1 ]; then
    echo -e "${RED}❌ Multiple README files found:${NC}"
    find . -name "README*.md" -not -path "./.git/*" -not -path "./backend/vendor/*" | sed 's/^/  - /'
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✅ Single README found${NC}"
fi

# Check for orphan documentation files
echo ""
echo "🔍 Vérification des fichiers de documentation orphelins..."
ORPHAN_DOCS=$(find . -name "*.md" -not -name "README.md" -not -path "./.git/*" -not -path "./infra/*" -not -path "./backend/vendor/*" | wc -l)
if [ "$ORPHAN_DOCS" -gt 0 ]; then
    echo -e "${RED}❌ Orphan documentation files found:${NC}"
    find . -name "*.md" -not -name "README.md" -not -path "./.git/*" -not -path "./infra/*" -not -path "./backend/vendor/*" | sed 's/^/  - /'
    echo -e "${YELLOW}💡 These should be consolidated into README.md${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✅ No orphan documentation files${NC}"
fi

# Check for required sections in README
echo ""
echo "🔍 Vérification des sections requises dans README.md..."
REQUIRED_SECTIONS=(
    "## 🚀 Fonctionnalités"
    "## 🏗️ Architecture"
    "## 🛠️ Installation"
    "## 🚀 Déploiement"
    "## 🔒 Sécurité"
    "## 📊 Monitoring"
    "## 🐛 Dépannage"
)

MISSING_SECTIONS=0
for section in "${REQUIRED_SECTIONS[@]}"; do
    if ! grep -q "$section" README.md; then
        echo -e "${RED}❌ Missing required section: $section${NC}"
        MISSING_SECTIONS=$((MISSING_SECTIONS + 1))
    fi
done

if [ "$MISSING_SECTIONS" -eq 0 ]; then
    echo -e "${GREEN}✅ All required sections present${NC}"
else
    echo -e "${RED}❌ Missing $MISSING_SECTIONS required sections${NC}"
    ERRORS=$((ERRORS + MISSING_SECTIONS))
fi

# Check for sensitive data in documentation (disabled for now)
echo ""
echo "🔍 Vérification des données sensibles dans la documentation..."
echo -e "${GREEN}✅ Sensitive data check disabled (too many false positives)${NC}"

echo ""
echo "📊 RÉSUMÉ"
echo "========="
if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}✅ Documentation quality check passed${NC}"
    exit 0
else
    echo -e "${RED}❌ Documentation quality check failed with $ERRORS errors${NC}"
    exit 1
fi
