# Receipt API - Scanner de Reçus avec IA

Application web pour scanner et analyser des reçus en utilisant Google Document AI et Google Sheets.

## 🚀 Vue d'ensemble

Cette application permet de :
- Scanner des reçus via l'interface web
- Analyser automatiquement le contenu avec Google Document AI
- Sauvegarder les données dans Google Sheets
- Gérer l'authentification Google OAuth

## 🎨 Identité Visuelle & Favicon

### Brand Identity
- **Nom de marque** : **Scan2Sheet**
- **Concept d'icône** : Ticket + faisceau de scan (flat, professionnel/technologique, fond transparent)
- **Palette de couleurs** :
  - Primaire : `#1A73E8` (Bleu Google)
  - Accent : `#34A853` (Vert Google)
  - Arrière-plan : `#FFFFFF` (Blanc)
  - Texte neutre : `#0F172A` (Gris foncé)

### Icônes & Favicon
- **Dossier** : `frontend/assets/icons/`
- **Formats requis** : ICO (16/32/48), PNG (16/32/48/64), Apple Touch (180×180), Web App (192×192 & 512×512), Maskable (192×192 & 512×512), Safari pinned tab (SVG monochrome)
- **Manifest** : `frontend/manifest.json`
- **Références** : Intégrées dans `frontend/index.html`

#### Fichiers d'icônes générés
```
frontend/assets/icons/
├── icon-master.svg              # Master vectoriel (512×512)
├── favicon.svg                  # Favicon principal
├── icon-16.svg                  # 16×16px
├── icon-32.svg                  # 32×32px
├── icon-48.svg                  # 48×48px
├── icon-64.svg                  # 64×64px
├── apple-touch-icon.svg         # 180×180px (iOS)
├── icon-192.svg                 # 192×192px (PWA)
├── icon-512.svg                 # 512×512px (PWA)
├── icon-192-maskable.svg        # 192×192px (PWA maskable)
├── icon-512-maskable.svg        # 512×512px (PWA maskable)
├── safari-pinned-tab.svg        # Monochrome (Safari)
└── ICON_PROMPT.md               # Instructions de régénération
```

#### Références dans `frontend/index.html`
```html
<!-- Favicon & Icons -->
<link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg" />
<link rel="icon" type="image/svg+xml" sizes="16x16" href="assets/icons/icon-16.svg" />
<link rel="icon" type="image/svg+xml" sizes="32x32" href="assets/icons/icon-32.svg" />
<link rel="icon" type="image/svg+xml" sizes="48x48" href="assets/icons/icon-48.svg" />
<link rel="icon" type="image/svg+xml" sizes="64x64" href="assets/icons/icon-64.svg" />

<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.svg" />

<!-- Safari Pinned Tab -->
<link rel="mask-icon" href="assets/icons/safari-pinned-tab.svg" color="#1A73E8" />

<!-- Web App Manifest -->
<link rel="manifest" href="manifest.json" />

<!-- Theme Colors -->
<meta name="theme-color" content="#1A73E8" />
<meta name="msapplication-TileColor" content="#1A73E8" />
```

#### Configuration du manifest.json
- **name** : "Scan2Sheet - Scanner de Tickets vers Google Sheets"
- **short_name** : "Scan2Sheet"
- **theme_color** : `#1A73E8`
- **background_color** : `#FFFFFF`
- **display** : "standalone"
- **scope** : "/"
- **start_url** : "/"

#### Régénération des icônes
```bash
# Générer tous les formats depuis le master SVG
./scripts/generate-favicons-node.js

# Ou utiliser ImageMagick (si disponible)
./scripts/generate-favicons.sh
```

#### Cache et Performance
- **Icônes** : Cache 1 an (`max-age=31536000, immutable`)
- **Manifest** : Cache 24h (`max-age=86400`)
- **Versioning** : Utiliser `?v=1` ou noms de fichiers hashés pour les mises à jour

## 🚀 Pipeline de Déploiement Cloud Build → Cloud Run

### Vue d'ensemble
Scan2Sheet utilise un pipeline de déploiement automatisé basé sur Google Cloud Build et Cloud Run pour assurer des déploiements fiables et sécurisés.

### Architecture du Pipeline
```
GitHub Push → Cloud Build Trigger → Build Image → Push to Artifact Registry → Deploy to Cloud Run → Smoke Tests → Notification
```

### Configuration GCP
- **Projet** : `264113083582`
- **Région** : `europe-west9` (Paris)
- **Service Cloud Run** : `receipt-parser`
- **Artifact Registry** : `receipt-api` (EU-West)
- **Min Instances** : 1 (pour éviter le cold start)
- **Concurrency** : 1 (optimisé pour l'usage)

### Triggers Automatiques
- **Staging** : Branche `staging` → Déploiement automatique
- **Production** : Branche `main` → Déploiement après validation manuelle

### Étapes du Pipeline

#### 1. Pre-build Quality Gates
- Validation de la structure des fichiers
- Vérification de l'existence du Dockerfile
- Contrôles de syntaxe de base

#### 2. Build et Push de l'Image
- Build Docker depuis la racine du projet
- Tag avec `$SHORT_SHA` et `latest`
- Push vers Artifact Registry (EU-West)
- Timeout : 10 minutes

#### 3. Déploiement Cloud Run
- Déploiement avec configuration optimisée
- Variables d'environnement depuis Secret Manager
- Configuration de ressources (512Mi RAM, 1 CPU)
- Port 8080 exposé

#### 4. Tests de Smoke
- Vérification des endpoints principaux
- Tests de performance
- Validation du branding Scan2Sheet
- Retry automatique (3 tentatives)

#### 5. Notifications
- Email de succès/échec
- Logs vers Cloud Logging
- Résumé des actions à effectuer

### Configuration des Secrets
Les secrets sont gérés via Google Secret Manager :

```bash
# Secrets requis
sa-key                 # Clé JSON du service account
oauth-client-id        # ID client OAuth Google
spreadsheet-id         # ID du Google Sheet
gcp-project-id         # ID du projet GCP
gcp-processor-id       # ID du processeur Document AI
debug-mode             # Mode debug (true/false)
```

### Permissions IAM (Least Privilege)

#### Service Account Cloud Build
- `roles/artifactregistry.writer` - Push d'images
- `roles/run.admin` - Déploiement Cloud Run
- `roles/secretmanager.secretAccessor` - Accès aux secrets
- `roles/logging.logWriter` - Écriture de logs
- `roles/iam.serviceAccountUser` - Utilisation du service account

#### Service Account Application
- `roles/documentai.apiUser` - Document AI
- `roles/sheets.editor` - Google Sheets
- `roles/run.invoker` - Appels Cloud Run

### Commandes de Déploiement

#### Configuration Initiale
```bash
# 1. Configuration des ressources GCP
./scripts/setup-gcp-resources.sh

# 2. Configuration des triggers Cloud Build
./scripts/setup-cloud-build-triggers.sh
```

#### Déploiement Manuel
```bash
# Staging
gcloud builds triggers run scan2sheet-staging-deploy --branch=staging

# Production
gcloud builds triggers run scan2sheet-production-deploy --branch=main
```

#### Tests et Validation
```bash
# Tests de smoke locaux
./scripts/smoke-tests.sh http://localhost:8080

# Tests sur un environnement déployé
./scripts/smoke-tests.sh https://your-service-url.run.app
```

#### Rollback
```bash
# Rollback vers la révision précédente
./scripts/rollback-deployment.sh staging
./scripts/rollback-deployment.sh production
```

### Monitoring et Logs
- **Cloud Run Console** : https://console.cloud.google.com/run/detail/europe-west9/receipt-parser?project=264113083582
- **Build Logs** : https://console.cloud.google.com/cloud-build/builds?project=264113083582
- **Logs Application** : Cloud Logging avec filtres par service

### Coûts et Optimisations
- **Min Instances = 1** : Évite le cold start mais coûte ~5€/mois
- **Concurrency = 1** : Optimisé pour les tâches de traitement de documents
- **Memory = 512Mi** : Suffisant pour l'application PHP/Apache
- **CPU = 1** : Équilibré entre performance et coût

### Sécurité
- **Ingress** : Public (all traffic) - Service web accessible
- **VPC** : Pas de VPC connector nécessaire pour l'usage actuel
- **Secrets** : Gérés via Secret Manager, jamais dans le code
- **HTTPS** : Forcé par Cloud Run
- **CSP** : Content Security Policy configurée

### Troubleshooting

#### Build Fails
1. Vérifier les logs Cloud Build
2. Valider la configuration des secrets
3. Tester le build localement : `docker build -t test .`

#### Deploy Fails
1. Vérifier les permissions IAM
2. Valider la configuration Cloud Run
3. Contrôler les quotas et limites

#### Smoke Tests Fail
1. Vérifier la disponibilité du service
2. Contrôler les endpoints API
3. Valider la configuration des secrets

### Documentation Technique
- **cloudbuild.yaml** : Configuration principale du pipeline
- **scripts/setup-*.sh** : Scripts de configuration
- **scripts/smoke-tests.sh** : Tests automatisés
- **scripts/rollback-*.sh** : Gestion des rollbacks

## 🏗️ Architecture

### Frontend (SPA)
- **Localisation** : `frontend/`
- **Technologies** : HTML5, CSS3, JavaScript (Vanilla)
- **Frameworks** : Bootstrap 5.3.3
- **Point d'entrée** : `frontend/index.html`
- **Assets** : `frontend/assets/` (CSS, JS, images)

### Backend (API)
- **Localisation** : `backend/`
- **Technologies** : PHP 8.1, Apache
- **Point d'entrée** : `backend/index.php`
- **Logique métier** : `backend/app.php`
- **Initialisation** : `backend/bootstrap.php`

### Infrastructure
- **Localisation** : `infra/`
- **Docker Compose** : `infra/docker-compose.yml`
- **Variables d'environnement** : `infra/.env`

## 🛠️ Installation et Développement Local

### Prérequis
- Docker et Docker Compose
- Git
- Compte Google Cloud avec APIs activées

### Configuration Initiale

1. **Cloner le repository**
   ```bash
   git clone <repository-url>
   cd receipt-api-local-google-parser
   ```

2. **Configurer les credentials Google Cloud**
   ```bash
   # Créer le dossier pour les credentials
   mkdir -p backend/keys
   
   # Placer votre fichier de service account
   cp path/to/your/sa-key.json backend/keys/sa-key.json
   ```

3. **Configurer les variables d'environnement**
   ```bash
   # Copier le template
   cp .env.example .env
   
   # Éditer les variables
   nano .env
   ```

### Variables d'Environnement

| Variable | Description | Exemple |
|----------|-------------|---------|
| `GOOGLE_APPLICATION_CREDENTIALS` | Chemin vers le fichier de credentials | `/var/www/html/api/keys/sa-key.json` |
| `GOOGLE_OAUTH_CLIENT_ID` | Client ID OAuth Google | `123456789.apps.googleusercontent.com` |
| `SPREADSHEET_ID` | ID de la feuille Google Sheets | `1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms` |
| `GCP_PROJECT_ID` | ID du projet Google Cloud | `my-project-123` |
| `GCP_PROCESSOR_ID` | ID du processeur Document AI | `1234567890` |
| `DEBUG` | Mode debug (0/1) | `1` |

### Démarrage Local

```bash
# Démarrer l'application
make up

# Vérifier le statut
make status

# Voir les logs
make logs

# Arrêter l'application
make down
```

### Scripts Disponibles

#### Développement Local (Makefile)
```bash
# Gestion des containers
make up          # Démarrer l'application
make down        # Arrêter l'application
make restart     # Redémarrer l'application
make ps          # Vérifier le statut des containers
make logs        # Voir les logs

# Développement
make install     # Installer les dépendances Composer
make sh-app      # Shell dans le container
make lint        # Linter le code (JS + PHP)
make format      # Formater le code
```

#### Tests et Validation
```bash
# Tests complets
./scripts/test-all.sh

# Tests spécifiques
./scripts/test-health.sh
./scripts/test-credential-*.sh
./scripts/check-gcp-readiness.sh
```

#### Production
En production, le Makefile n'est **PAS utilisé**. Le déploiement se fait via :
- **GitHub Actions** : CI/CD automatique
- **Cloud Run** : Déploiement serverless
- **Secret Manager** : Gestion des credentials

## 🚀 Déploiement Production (Google Cloud Platform)

### Prérequis GCP
- Compte Google Cloud avec facturation activée
- APIs activées : Cloud Run, Secret Manager, Document AI, Sheets API
- Service Account avec permissions appropriées

### Configuration des Secrets

1. **Créer un Service Account**
   ```bash
   gcloud iam service-accounts create receipt-api-sa \
     --display-name="Receipt API Service Account"
   ```

2. **Attribuer les rôles IAM**
   ```bash
   gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
     --member="serviceAccount:receipt-api-sa@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
     --role="roles/documentai.apiUser"
   
   gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
     --member="serviceAccount:receipt-api-sa@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
     --role="roles/sheets.editor"
   ```

3. **Créer le secret dans Secret Manager**
   ```bash
   gcloud secrets create sa-key \
     --data-file=backend/keys/sa-key.json
   ```

### Configuration GitHub

Dans votre repository GitHub, ajoutez ces secrets :
- `GCP_SA_KEY` : Contenu du fichier sa-key.json
- `GCP_PROJECT_ID` : ID de votre projet GCP
- `GOOGLE_OAUTH_CLIENT_ID` : Client ID OAuth
- `SPREADSHEET_ID` : ID de la feuille Sheets
- `GCP_PROCESSOR_ID` : ID du processeur Document AI

### Déploiement Automatique

Le déploiement se fait automatiquement via GitHub Actions :

```bash
# Pousser le code sur main
git add .
git commit -m "feat: ready for production"
git push origin main
```

### Vérification du Déploiement

```bash
# Vérifier le service
gcloud run services list --region=europe-west1

# Tester l'application
curl https://your-service-url/api/health
curl https://your-service-url/api/ready
```

## 🧪 Tests et Validation

### Tests Locaux

```bash
# Tests complets
./scripts/test-all.sh

# Tests de santé
./scripts/test-health.sh

# Tests de sécurité
./scripts/check-gcp-security.sh

# Tests de préparation GCP
./scripts/check-gcp-readiness.sh
```

### Tests de Production

```bash
# Tests des endpoints
curl https://your-service-url/api/health
curl https://your-service-url/api/ready
curl https://your-service-url/api/config

# Tests d'authentification
curl https://your-service-url/api/auth/me
```

## 📊 Monitoring et Logs

### Logs Locaux
```bash
# Voir tous les logs
make logs

# Logs du backend uniquement
docker logs receipt-api-local-google-parser-backend-1

# Logs en temps réel
docker logs -f receipt-api-local-google-parser-backend-1
```

### Logs Production
```bash
# Logs du service Cloud Run
gcloud logging read "resource.type=cloud_run_revision" --limit=50

# Logs d'erreur
gcloud logging read "severity>=ERROR" --limit=20
```

### Métriques
```bash
# Métriques de performance
gcloud monitoring metrics list --filter="resource.type=cloud_run_revision"

# Utilisation des ressources
gcloud run services describe receipt-api --region=europe-west1
```

## 🔧 Configuration Avancée

### Content Security Policy (CSP)
La CSP est configurée pour permettre :
- Images blob (prévisualisation locale)
- Images data (base64)
- Images HTTPS (Google Sheets)
- Scripts Google OAuth

### Headers de Sécurité
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin

### Gestion des Credentials
- **Local** : Fichier `backend/keys/sa-key.json`
- **Production** : Google Secret Manager
- **Rotation** : Automatique via Secret Manager

## 🛠️ Outils et Technologies

### Backend
- **PHP 8.1** : Langage principal
- **Apache** : Serveur web
- **Composer** : Gestion des dépendances
- **Google Cloud Client Library** : Intégration GCP

### Frontend
- **HTML5/CSS3/JavaScript** : Technologies de base
- **Bootstrap 5.3.3** : Framework CSS
- **Google Identity** : Authentification OAuth

### Infrastructure
- **Docker** : Containerisation
- **Docker Compose** : Orchestration locale
- **Google Cloud Run** : Déploiement production
- **GitHub Actions** : CI/CD

### Outils de Développement
- **Makefile** : Scripts d'automatisation
- **PHPCS** : Analyse de code PHP
- **Git** : Contrôle de version

## 🚨 Troubleshooting

### Problèmes Courants

#### Application ne démarre pas
```bash
# Vérifier les logs
make logs

# Vérifier les containers
docker ps -a

# Redémarrer
make restart
```

#### Erreurs de credentials
```bash
# Vérifier le fichier de credentials
ls -la backend/keys/sa-key.json

# Tester les credentials
./scripts/check-credentials.sh
```

#### Erreurs de CSP (images blob)
```bash
# Vérifier la CSP
curl -I http://localhost:8080 | grep -i "content-security-policy"

# Tester les images blob
./scripts/test-csp-blob-images.sh
```

#### Erreurs d'API
```bash
# Tester les endpoints
curl http://localhost:8080/api/health
curl http://localhost:8080/api/ready

# Vérifier les logs
make logs
```

### Commandes de Diagnostic

```bash
# Vérifier la santé de l'application
./scripts/test-health.sh

# Vérifier la sécurité
./scripts/check-gcp-security.sh

# Vérifier la préparation GCP
./scripts/check-gcp-readiness.sh

# Tests complets
./scripts/test-all.sh
```

## 📚 Documentation Technique

### Structure du Projet
```
receipt-api-local-google-parser/
├── frontend/                 # Application SPA
│   ├── index.html           # Point d'entrée
│   ├── assets/              # CSS, JS, images
│   └── .htaccess            # Configuration Apache
├── backend/                 # API Backend
│   ├── index.php            # Point d'entrée API
│   ├── app.php              # Logique métier
│   ├── bootstrap.php        # Initialisation
│   ├── composer.json        # Dépendances PHP
│   └── keys/                # Credentials (local)
├── infra/                   # Infrastructure
│   ├── docker-compose.yml   # Orchestration locale
│   └── .env                 # Variables d'environnement
├── scripts/                 # Scripts d'automatisation
├── docs/                    # Documentation technique
├── .github/workflows/       # CI/CD GitHub Actions
├── Dockerfile               # Build production
├── Makefile                 # Scripts de développement
└── README.md                # Cette documentation
```

### Endpoints API

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/health` | GET | Vérification de santé |
| `/api/ready` | GET | Vérification de readiness |
| `/api/config` | GET | Configuration de l'application |
| `/api/auth/me` | GET | Informations utilisateur |
| `/api/sheets` | GET | Liste des feuilles |
| `/api/sheets/write` | POST | Écriture dans Sheets |
| `/api/scan` | POST | Analyse d'un document |
| `/api/scan/batch` | POST | Analyse en lot |

### Politiques de Logging

#### Format des Logs
```json
{
  "timestamp": "2025-01-06T10:30:00Z",
  "level": "INFO|WARNING|ERROR",
  "message": "Description de l'événement",
  "context": {
    "user_id": "user@example.com",
    "request_id": "uuid",
    "endpoint": "/api/scan"
  }
}
```

#### Niveaux de Log
- **ERROR** : Erreurs critiques nécessitant une intervention
- **WARNING** : Problèmes non critiques mais à surveiller
- **INFO** : Informations générales sur le fonctionnement

#### Données Sensibles
Les logs sont automatiquement nettoyés des données sensibles :
- Tokens d'authentification
- Clés privées
- Emails utilisateurs
- IDs de documents

## 🔄 Maintenance et Mises à Jour

### Rotation des Secrets
```bash
# Créer une nouvelle clé
gcloud iam service-accounts keys create new-sa-key.json \
  --iam-account="receipt-api-sa@YOUR_PROJECT_ID.iam.gserviceaccount.com"

# Mettre à jour le secret
gcloud secrets versions add sa-key --data-file=new-sa-key.json

# Supprimer l'ancienne clé
gcloud iam service-accounts keys delete OLD_KEY_ID \
  --iam-account="receipt-api-sa@YOUR_PROJECT_ID.iam.gserviceaccount.com"
```

### Mise à Jour de l'Application
```bash
# Déploiement automatique via GitHub Actions
git add .
git commit -m "feat: update application"
git push origin main
```

### Monitoring des Performances
```bash
# Métriques Cloud Run
gcloud monitoring metrics list --filter="resource.type=cloud_run_revision"

# Logs de performance
gcloud logging read "severity>=WARNING" --limit=100
```

## 📞 Support et Ressources

### Documentation Officielle
- [Google Cloud Run](https://cloud.google.com/run/docs)
- [Document AI](https://cloud.google.com/document-ai/docs)
- [Google Sheets API](https://developers.google.com/sheets/api)
- [Secret Manager](https://cloud.google.com/secret-manager/docs)

### Guides de Sécurité
- [IAM Best Practices](https://cloud.google.com/iam/docs/using-iam-securely)
- [Secret Manager Best Practices](https://cloud.google.com/secret-manager/docs/best-practices)
- [Cloud Run Security](https://cloud.google.com/run/docs/securing)

### Scripts Utiles
- `./scripts/test-all.sh` : Tests complets
- `./scripts/check-gcp-readiness.sh` : Vérification pré-déploiement
- `./scripts/check-gcp-security.sh` : Vérification sécurité
- `./scripts/test-health.sh` : Tests de santé

---

🎉 **Receipt API** est maintenant prêt à scanner et analyser vos reçus avec l'intelligence artificielle de Google Cloud !