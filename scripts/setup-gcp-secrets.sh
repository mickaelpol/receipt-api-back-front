#!/bin/bash

# Script pour configurer les secrets dans Google Secret Manager
# Usage: ./scripts/setup-gcp-secrets.sh

set -e

echo "🔐 Configuration des secrets dans Google Secret Manager..."

# Vérifier que gcloud est installé
if ! command -v gcloud &> /dev/null; then
    echo "❌ gcloud CLI n'est pas installé"
    echo "📦 Installer avec: https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Vérifier l'authentification
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" &> /dev/null; then
    echo "❌ gcloud non authentifié"
    echo "🔐 S'authentifier avec: gcloud auth login"
    exit 1
fi

# Définir le projet
PROJECT_ID="scan-document-ai"
echo "📋 Projet GCP: $PROJECT_ID"

# Activer l'API Secret Manager si nécessaire
echo "🔧 Activation de l'API Secret Manager..."
gcloud services enable secretmanager.googleapis.com --project=$PROJECT_ID

echo ""
echo "📝 Configuration des secrets..."
echo ""

# Fonction pour créer ou mettre à jour un secret
create_or_update_secret() {
    local secret_name=$1
    local secret_description=$2
    
    echo "🔑 Configuration de: $secret_name"
    echo "   Description: $secret_description"
    echo "   Entrez la valeur (ou Ctrl+C pour passer):"
    
    # Lire la valeur du secret
    read -r secret_value
    
    if [ -z "$secret_value" ]; then
        echo "   ⏭️  Valeur vide, secret ignoré"
        return
    fi
    
    # Vérifier si le secret existe déjà
    if gcloud secrets describe $secret_name --project=$PROJECT_ID &> /dev/null; then
        echo "   ♻️  Secret existant, création d'une nouvelle version..."
        echo -n "$secret_value" | gcloud secrets versions add $secret_name \
            --data-file=- \
            --project=$PROJECT_ID
    else
        echo "   ✨ Création du nouveau secret..."
        echo -n "$secret_value" | gcloud secrets create $secret_name \
            --data-file=- \
            --replication-policy="automatic" \
            --project=$PROJECT_ID
    fi
    
    echo "   ✅ $secret_name configuré"
    echo ""
}

# Configuration des secrets
echo "═══════════════════════════════════════════════════════"
echo "1. OAuth Client ID"
echo "═══════════════════════════════════════════════════════"
create_or_update_secret "oauth-client-id" "Google OAuth Client ID (format: xxx.googleusercontent.com)"

echo "═══════════════════════════════════════════════════════"
echo "2. Spreadsheet ID"
echo "═══════════════════════════════════════════════════════"
create_or_update_secret "spreadsheet-id" "ID du Google Sheet depuis l'URL"

echo "═══════════════════════════════════════════════════════"
echo "3. GCP Project ID"
echo "═══════════════════════════════════════════════════════"
echo "🔑 Configuration de: gcp-project-id"
echo "   Valeur par défaut: $PROJECT_ID (appuyez sur Entrée pour confirmer ou entrez une autre valeur):"
read -r gcp_project_value
if [ -z "$gcp_project_value" ]; then
    gcp_project_value=$PROJECT_ID
fi

if gcloud secrets describe gcp-project-id --project=$PROJECT_ID &> /dev/null; then
    echo -n "$gcp_project_value" | gcloud secrets versions add gcp-project-id \
        --data-file=- \
        --project=$PROJECT_ID
else
    echo -n "$gcp_project_value" | gcloud secrets create gcp-project-id \
        --data-file=- \
        --replication-policy="automatic" \
        --project=$PROJECT_ID
fi
echo "   ✅ gcp-project-id configuré"
echo ""

echo "═══════════════════════════════════════════════════════"
echo "4. GCP Processor ID (Document AI)"
echo "═══════════════════════════════════════════════════════"
create_or_update_secret "gcp-processor-id" "ID du processeur Document AI"

echo "═══════════════════════════════════════════════════════"
echo "5. Allowed Emails"
echo "═══════════════════════════════════════════════════════"
create_or_update_secret "allowed-emails" "Liste des emails autorisés (séparés par des virgules)"

echo "═══════════════════════════════════════════════════════"
echo "6. WHO_COLUMNS Configuration"
echo "═══════════════════════════════════════════════════════"
create_or_update_secret "who-columns" "JSON: {\"Nom\":[\"A\",\"B\",\"C\"],\"Autre\":[\"D\",\"E\",\"F\"]}"

echo ""
echo "🎉 Configuration des secrets terminée !"
echo ""

# Configuration des permissions IAM
echo "🔐 Configuration des permissions IAM..."
SERVICE_ACCOUNT="docai-sa@${PROJECT_ID}.iam.gserviceaccount.com"

echo "   Attribution des permissions à: $SERVICE_ACCOUNT"

# Accorder l'accès aux secrets
for secret in oauth-client-id spreadsheet-id gcp-project-id gcp-processor-id allowed-emails who-columns; do
    gcloud secrets add-iam-policy-binding $secret \
        --member="serviceAccount:$SERVICE_ACCOUNT" \
        --role="roles/secretmanager.secretAccessor" \
        --project=$PROJECT_ID \
        &> /dev/null || echo "   ⚠️  Erreur lors de l'attribution des permissions pour $secret"
done

echo "   ✅ Permissions IAM configurées"
echo ""

# Afficher les secrets configurés
echo "📋 Secrets configurés dans Google Secret Manager:"
gcloud secrets list --project=$PROJECT_ID --format="table(name,createTime)"

echo ""
echo "🔗 Console GCP Secret Manager:"
echo "   https://console.cloud.google.com/security/secret-manager?project=$PROJECT_ID"
echo ""
echo "✅ Tous les secrets sont maintenant stockés de manière sécurisée dans GCP !"
echo "   Aucun secret n'est stocké dans GitHub ou dans le code."
