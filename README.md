# 🧾 Receipt Scanner API - Google Cloud

Application web de scan de tickets de caisse utilisant Google Document AI pour extraire automatiquement les données (fournisseur, date, montant) et les sauvegarder dans Google Sheets.

[![Tests](https://github.com/mickaelpol/receipt-api-back-front/workflows/Tests/badge.svg)](https://github.com/mickaelpol/receipt-api-back-front/actions)
[![Code Coverage](https://img.shields.io/badge/coverage-49.58%25-orange.svg)](backend/coverage/html/index.html)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## 📋 Table des matières

- [Fonctionnalités](#-fonctionnalités)
- [Architecture](#-architecture)
- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Utilisation](#-utilisation)
- [Tests](#-tests)
- [Déploiement sur GitHub et Production](#-déploiement-sur-github-et-production)
- [API Endpoints](#-api-endpoints)
- [Développement](#-développement)
- [Troubleshooting](#-troubleshooting)
- [Licence](#-licence)

---

## ✨ Fonctionnalités

### Backend
- **Scan OCR intelligent** - Google Document AI pour extraction automatique des données
- **Cache optimisé** - Réduction des coûts API (SHA256 hashing)
- **Authentification OAuth 2.0** - Google Sign-In avec whitelist d'emails
- **Rate Limiting** - Protection contre les abus (file-based, concurrency-safe)
- **Écriture Sheets optimiste** - Gestion des conflits avec retry automatique
- **Logging structuré** - JSON avec masquage des données sensibles
- **Health checks** - `/health` (liveness) + `/ready` (readiness)

### Frontend
- **Interface responsive** - Bootstrap 5, mobile-friendly
- **Scan simple ou batch** - Jusqu'à 10 tickets simultanés
- **Prévisualisation en temps réel** - Voir le ticket avant validation
- **Mode hors-ligne** - Service Worker avec cache (future version)

### Infrastructure
- **Docker** - Développement local avec hot reload
- **Cloud Run** - Déploiement serverless auto-scalable
- **GitHub Actions** - CI/CD automatique avec tests
- **Secret Manager** - Gestion sécurisée des credentials

---

## 🏗️ Architecture

### Stack technique
- **Backend:** PHP 8.1 (Apache + mod_rewrite)
- **Frontend:** Vanilla JavaScript ES6+ (Bootstrap 5)
- **Base de données:** Google Sheets API
- **OCR:** Google Document AI (Receipt Parser)
- **Auth:** Google OAuth 2.0 + Service Account
- **Infrastructure:** Docker, Cloud Build, Cloud Run

### Flow de requête
```
┌─────────┐    OAuth     ┌──────────┐   Bearer Token   ┌──────────┐
│ Browser │─────────────>│ Frontend │─────────────────>│ Backend  │
└─────────┘              └──────────┘                  └──────────┘
                                                             │
                          ┌──────────────────────────────────┤
                          │                                  │
                    ┌─────▼──────┐                    ┌──────▼────────┐
                    │ Document AI│                    │ Sheets API    │
                    │  (OCR)     │                    │  (Storage)    │
                    └────────────┘                    └───────────────┘
```

### Structure du projet
```
receipt-api-local-google-parser/
├── backend/
│   ├── app.php                    # Fonctions métier (pure, pas d'exécution)
│   ├── index.php                  # Router et handlers (runtime logic)
│   ├── bootstrap.php              # Validation env + autoload
│   ├── RateLimiter.php            # Classe rate limiting
│   ├── rate_limit_middleware.php  # Middleware rate limiting
│   ├── composer.json              # Dépendances PHP
│   ├── keys/                      # Service Account JSON (gitignored)
│   └── tests/                     # Tests unitaires et intégration
│       ├── Unit/                  # Tests unitaires (128 tests)
│       └── Integration/           # Tests d'intégration
├── frontend/
│   ├── index.html                 # SPA principale
│   └── assets/
│       ├── css/app.css            # Styles custom
│       ├── js/app.js              # Logique frontend
│       └── libs/                  # Bootstrap 5
├── infra/
│   ├── Dockerfile                 # Image production
│   ├── docker-compose.yml         # Stack développement
│   ├── docker/start.sh            # Script de démarrage Cloud Run
│   └── apache/000-default.conf    # Config Apache
├── .github/workflows/
│   └── tests.yml                  # CI/CD GitHub Actions
├── .githooks/                     # Git hooks (pre-commit, pre-push)
├── cloudbuild.yaml                # Google Cloud Build config
├── phpunit.xml                    # Configuration PHPUnit
├── Makefile                       # Commandes automatisées
├── .env.example                   # Template variables d'environnement
└── README.md                      # Ce fichier
```

---

## 📦 Prérequis

### Développement local
- **Docker** 20.10+ et **Docker Compose** 2.0+
- **Make** (optionnel, pour commandes simplifiées)
- **Git** 2.30+

### Google Cloud Platform
- **Projet GCP** avec facturation activée
- **APIs activées:**
  - Document AI API
  - Google Sheets API
  - Secret Manager API
  - Cloud Run API
  - Artifact Registry API

### Credentials Google
1. **OAuth Client ID** (pour authentification frontend)
   - Type: "Web application"
   - Origines autorisées: `http://localhost:8080`, `https://votre-domaine.com`

2. **Service Account** (pour accès backend aux APIs)
   - Rôles: `Document AI API User`, `Secret Manager Secret Accessor`
   - Clé JSON téléchargée dans `backend/keys/sa-key.json`

3. **Google Spreadsheet**
   - Partagé avec l'email du Service Account (éditeur)
   - Format: colonnes configurables via `WHO_COLUMNS`

---

## 🚀 Installation

### 1. Cloner le repository

```bash
git clone https://github.com/votre-username/receipt-api-local-google-parser.git
cd receipt-api-local-google-parser
```

### 2. Installer les Git hooks (CRITIQUE)

Ces hooks préviennent les commits accidentels de secrets:

```bash
make install-hooks
```

**Ou manuellement:**
```bash
cp .githooks/pre-commit .git/hooks/pre-commit
cp .githooks/pre-push .git/hooks/pre-push
chmod +x .git/hooks/pre-commit .git/hooks/pre-push
```

**Hooks installés:**
- **pre-commit:** Bloque les commits de `.env`, `*.json` (sauf package.json), API keys
- **pre-push:** Vérifie syntaxe PHP/JS, PHPCS, Docker config, demande confirmation pour `main`

### 3. Configurer les variables d'environnement

```bash
# Copier le template
cp infra/.env.example infra/.env

# Éditer avec vos valeurs
nano infra/.env  # ou vim, code, etc.
```

**Exemple `.env`:**
```bash
# Google Cloud
GCP_PROJECT_ID=votre-project-id
GCP_LOCATION=eu
GCP_PROCESSOR_ID=abc123...
GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/keys/sa-key.json

# OAuth
GOOGLE_OAUTH_CLIENT_ID=123456789-abc.apps.googleusercontent.com

# Spreadsheet
SPREADSHEET_ID=1a2b3c4d5e6f7g8h9i0j
DEFAULT_SHEET=Dépenses 2025

# Sécurité
ALLOWED_EMAILS=user1@gmail.com,user2@example.com

# Configuration colonnes (JSON)
WHO_COLUMNS={"Sabrina":["K","L","M"],"Mickael":["O","P","Q"]}

# Optionnel
MAX_BATCH_UPLOADS=10
DEBUG=0
APP_ENV=local
```

### 4. Ajouter la clé Service Account

```bash
# Créer le dossier keys
mkdir -p backend/keys

# Copier votre clé JSON
cp ~/Downloads/votre-sa-key.json backend/keys/sa-key.json

# Vérifier les permissions
chmod 600 backend/keys/sa-key.json
```

### 5. Installer les dépendances PHP

```bash
# Via Docker (recommandé)
docker run --rm -v "$PWD/backend:/app" composer:2 install

# Ou localement si Composer installé
cd backend && composer install
```

### 6. Démarrer l'application

```bash
make up
```

**Ou manuellement:**
```bash
cd infra && docker-compose up -d
```

Attendre 5-10 secondes que Apache démarre, puis accéder à:
- **Frontend:** http://localhost:8080
- **API:** http://localhost:8080/api/config

---

## ⚙️ Configuration

### Variables d'environnement requises

| Variable | Description | Exemple |
|----------|-------------|---------|
| `GCP_PROJECT_ID` | ID du projet Google Cloud | `my-project-123456` |
| `GCP_PROCESSOR_ID` | ID du processeur Document AI | `abc123def456...` |
| `GOOGLE_OAUTH_CLIENT_ID` | Client ID OAuth pour frontend | `123-abc.apps.googleusercontent.com` |
| `SPREADSHEET_ID` | ID du Google Spreadsheet cible | `1a2b3c4d5e6f7g8h9i0j` |
| `ALLOWED_EMAILS` | Whitelist d'emails (comma-separated) | `user1@gmail.com,user2@gmail.com` |

### Variables optionnelles

| Variable | Description | Défaut |
|----------|-------------|--------|
| `DEFAULT_SHEET` | Nom de la feuille par défaut | `Sheet1` |
| `WHO_COLUMNS` | Mapping colonnes par personne (JSON) | `{"Sabrina":["K","L","M"]}` |
| `MAX_BATCH_UPLOADS` | Nombre max de scans en batch | `10` |
| `GCP_LOCATION` | Région GCP | `eu` |
| `DEBUG` | Mode debug (1/0) | `0` |
| `APP_ENV` | Environnement (local/prod) | `prod` |

### Format WHO_COLUMNS

```json
{
  "Sabrina": ["K", "L", "M"],
  "Mickael": ["O", "P", "Q"]
}
```

- **Colonne 1:** Nom du fournisseur (ex: "Carrefour")
- **Colonne 2:** Date au format `dd/mm/yyyy`
- **Colonne 3:** Montant total (ex: `25.50`)

**Contrainte:** Exactement 3 colonnes (lettres A-Z) par personne.

---

## 🎮 Utilisation

### Interface web

1. Ouvrir http://localhost:8080
2. Se connecter avec Google (email doit être dans `ALLOWED_EMAILS`)
3. Choisir le mode:
   - **Scan simple:** Prendre une photo → prévisualiser → valider
   - **Scan batch:** Prendre jusqu'à 10 photos → scanner tout

### Commandes Make disponibles

```bash
# Développement
make up              # Démarrer l'application
make down            # Arrêter l'application
make restart         # Redémarrer (nécessaire après changement .env)
make logs            # Afficher les logs en temps réel
make sh-app          # Ouvrir un shell dans le container

# Tests
make test            # Lancer tous les tests
make test-unit       # Tests unitaires uniquement
make test-integration # Tests d'intégration uniquement
make test-coverage   # Générer rapport HTML de couverture
make test-coverage-text # Afficher couverture dans le terminal

# Qualité de code
make lint            # Linter PHP + JavaScript
make format          # Auto-formatter (phpcbf)
make check-quality   # Vérifications complètes (lint + tests)

# Déploiement
make deploy-direct   # Déploiement direct sur Cloud Run
make smoke-test      # Tests de santé (local)
make smoke-test-staging  # Tests sur staging
make smoke-test-prod     # Tests sur production

# Utilitaires
make install-hooks   # Installer les Git hooks
make setup-gcp-secrets # Configurer les secrets GCP
make cache-bust      # Regénérer les hashes de cache frontend
```

### API Usage (exemples cURL)

**Configuration publique:**
```bash
curl http://localhost:8080/api/config
```

**Authentification:**
```bash
curl -H "Authorization: Bearer <votre-token-google>" \
  http://localhost:8080/api/auth/me
```

**Scanner un ticket (base64):**
```bash
curl -X POST http://localhost:8080/api/scan \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "imageBase64": "data:image/jpeg;base64,/9j/4AAQ..."
  }'
```

**Écrire dans Sheets:**
```bash
curl -X POST http://localhost:8080/api/sheets/write \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "sheetName": "Dépenses 2025",
    "who": "Mickael",
    "supplier": "Carrefour",
    "dateISO": "2025-01-15",
    "total": 25.50
  }'
```

---

## 🧪 Tests

### Exécuter les tests

```bash
# Tous les tests (128 tests, ~11 secondes)
make test

# Tests unitaires uniquement
make test-unit

# Avec rapport de couverture
make test-coverage
open backend/coverage/html/index.html  # macOS
# ou
xdg-open backend/coverage/html/index.html  # Linux
```

### Couverture actuelle

**Global:** 49.58% (353/712 lignes)

| Fichier | Couverture | Détails |
|---------|------------|---------|
| `RateLimiter.php` | 84.31% | Classe rate limiting |
| `app.php` | 47.75% | 12/26 fonctions à 100% |
| `rate_limit_middleware.php` | 33.33% | Fonctions critiques testées |

**Fonctions 100% couvertes:**
- Parsing et validation (dates, colonnes, WHO_COLUMNS)
- Sécurité (bearer token, masking, credentials)
- Logging structuré
- Cache Document AI
- Conversions utilitaires

**Fonctions non testées (nécessitent mocks):**
- Appels HTTP externes
- OAuth validation
- Document AI processing
- Google Sheets API
- Fonctions avec `exit()` (sendJsonResponse, applyRateLimit)

Voir `RECAP.md` pour détails complets et roadmap tests.

### Structure des tests

```
backend/tests/
├── Unit/
│   ├── ColumnParsingTest.php       # Parsing colonnes WHO_COLUMNS
│   ├── DateParsingTest.php         # Validation dates
│   ├── DocumentAITest.php          # Extraction données DocAI
│   ├── DocumentAICacheTest.php     # Cache + cleanup
│   ├── GoogleCredentialsTest.php   # Validation credentials
│   ├── LoggingTest.php             # Logging structuré
│   ├── RateLimiterTest.php         # Rate limiting
│   ├── RateLimitMiddlewareTest.php # Middleware rate limiting
│   ├── SecurityTest.php            # Bearer token + masking
│   └── UtilityFunctionsTest.php    # Fonctions utilitaires
└── Integration/
    └── SheetsIntegrationTest.php   # Tests signatures API Sheets
```

---

## 🚀 Déploiement sur GitHub et Production

### Guide A à Z - Étapes détaillées

#### Étape 1: Vérifier l'état du repository Git

Avant tout commit, vérifier que les hooks sont installés et que le code est propre.

```bash
# Vérifier que les hooks Git sont installés
ls -la .git/hooks/
# Vous devez voir: pre-commit et pre-push (exécutables)

# Si manquants, les installer
make install-hooks

# Vérifier l'état de Git
git status
```

**Résultat attendu:**
```
On branch main
Changes not staged for commit:
  modified:   backend/app.php
  ...

Untracked files:
  README.md
  RECAP.md
```

#### Étape 2: Nettoyer les fichiers inutiles

Supprimer les fichiers de backup et temporaires qui ne doivent pas être commités:

```bash
# Supprimer les fichiers de backup
rm -f frontend/assets/css/app.css.backup
rm -f frontend/assets/css/app.css.backup-old-*
rm -f frontend/assets/js/app.js.backup
rm -f frontend/assets/libs/bootstrap/5.3.3/bootstrap.min.css.backup

# Vérifier qu'aucun fichier backup ne reste
find . -type f \( -name "*.backup*" -o -name "*~" \) -not -path "./vendor/*"
# Ne doit rien retourner
```

#### Étape 3: Vérifier le .gitignore

S'assurer que les fichiers sensibles sont bien ignorés:

```bash
# Vérifier le contenu du .gitignore
cat .gitignore | grep -E "(CLAUDE.md|\.env|backend/keys)"

# Résultat attendu:
# .env
# backend/keys/*.json
# CLAUDE.md
```

**IMPORTANT:** Ne jamais commiter:
- `.env` ou `.env.local`
- `backend/keys/*.json` (Service Account)
- `CLAUDE.md` (instructions Claude)
- Fichiers de credentials (`.key`, `.pem`, `.p12`)

#### Étape 4: Exécuter les tests localement

Avant de commiter, toujours vérifier que les tests passent:

```bash
# Lancer tous les tests
make test

# Résultat attendu:
# OK (128 tests, 349 assertions)
```

Si des tests échouent, les corriger avant de continuer.

```bash
# Optionnel: vérifier la couverture de code
make test-coverage-text

# Optionnel: vérifier la qualité du code
make lint
```

#### Étape 5: Ajouter les fichiers au staging Git

```bash
# Ajouter tous les fichiers modifiés et nouveaux
git add .

# OU ajouter sélectivement
git add README.md RECAP.md backend/app.php frontend/index.html

# Vérifier ce qui sera commité
git status

# Résultat attendu:
# Changes to be committed:
#   new file:   README.md
#   new file:   RECAP.md
#   modified:   backend/app.php
#   ...
```

**ATTENTION:** Si vous voyez des fichiers sensibles (`.env`, `sa-key.json`), **NE PAS** les ajouter:

```bash
# Les retirer du staging
git reset backend/keys/sa-key.json
git reset .env
```

#### Étape 6: Créer un commit

```bash
# Créer un commit avec un message descriptif
git commit -m "feat: add comprehensive documentation and cleanup backup files

- Add complete README.md with A-Z deployment guide
- Add RECAP.md with project status and recommendations
- Remove backup files (.backup, .backup-old-*)
- Update test coverage documentation (49.58%)
- Clean up unused markdown files"
```

**Convention de messages de commit:**
- `feat:` - Nouvelle fonctionnalité
- `fix:` - Correction de bug
- `docs:` - Documentation uniquement
- `test:` - Ajout ou modification de tests
- `refactor:` - Refactoring sans changement de fonctionnalité
- `chore:` - Tâches de maintenance (deps, config)

**Le hook pre-commit va s'exécuter automatiquement:**
```
🔍 Running pre-commit checks...
✓ No .env files found
✓ No service account keys found
✓ No API keys patterns found
✓ PHP syntax check passed
✓ JavaScript syntax check passed
✓ No large files (>1MB) found
✅ Pre-commit checks passed!
```

Si le hook bloque le commit, corriger les erreurs affichées.

#### Étape 7: Vérifier le commit local

```bash
# Voir le dernier commit
git log -1 --stat

# Vérifier les différences
git show HEAD
```

#### Étape 8: Pousser sur GitHub (branche de développement)

**IMPORTANT:** Pour la première fois, pusher sur une branche de développement, **PAS directement sur `main`**.

```bash
# Créer une branche de développement (si pas déjà fait)
git checkout -b dev

# Ou si la branche existe déjà
git checkout dev

# Merger main dans dev (si nécessaire)
git merge main

# Pousser la branche dev sur GitHub
git push origin dev
```

**Le hook pre-push va s'exécuter:**
```
🔍 Running pre-push checks...
✓ All tests passed (128/128)
✓ PHPCS passed
✓ composer.lock is synchronized
✓ Dockerfile is valid
✓ cloudbuild.yaml is valid
✅ Pre-push checks passed!
```

Si le hook bloque le push:
- Corriger les erreurs affichées (tests, linting, etc.)
- Re-commiter si nécessaire: `git commit --amend` ou nouveau commit
- Re-tenter le push

#### Étape 9: Créer une Pull Request sur GitHub

1. Aller sur GitHub: `https://github.com/votre-username/receipt-api-local-google-parser`
2. Cliquer sur "Compare & pull request" (bannière jaune)
3. **Base:** `main` ← **Compare:** `dev`
4. Remplir le titre et la description:

```markdown
## Changes
- Add comprehensive README.md with deployment guide
- Add RECAP.md with project status
- Clean up backup files

## Testing
- [x] All tests passing (128/128)
- [x] Code coverage: 49.58%
- [x] Linting passed
- [x] Local smoke tests passed

## Checklist
- [x] No sensitive data committed
- [x] Git hooks installed and passing
- [x] Documentation updated
- [x] Ready for production deployment
```

5. Cliquer "Create pull request"
6. Attendre les checks GitHub Actions (tests automatiques)
7. Si tout est vert ✅, merger la PR dans `main`

#### Étape 10: Pousser sur main (déploiement production)

**Option A: Via Pull Request (recommandé)**

Après avoir mergé la PR, récupérer les changements localement:

```bash
# Revenir sur main
git checkout main

# Récupérer les changements de GitHub
git pull origin main
```

**Option B: Push direct sur main (avec confirmation)**

```bash
# Basculer sur main
git checkout main

# Merger dev dans main
git merge dev

# Push sur main (déclenche le déploiement production)
git push origin main
```

**Le hook pre-push va demander confirmation pour main:**
```
⚠️  WARNING: You are pushing to 'main' branch!
This will trigger a production deployment.

Are you sure you want to continue? (yes/no): yes

🚀 Preparing assets for production...
✓ Cache-busting completed
✓ Asset manifest updated

🔍 Running pre-push checks...
[... checks ...]
✅ Push to main authorized!
```

**Taper `yes` pour confirmer.**

#### Étape 11: Vérifier le déploiement sur Cloud Run

Une fois poussé sur `main`, GitHub Actions va automatiquement:
1. Exécuter les tests
2. Builder l'image Docker
3. Pousser sur Artifact Registry
4. Déployer sur Cloud Run

**Suivre le déploiement:**

1. GitHub Actions: https://github.com/votre-username/receipt-api-local-google-parser/actions
2. Cloud Run Console: https://console.cloud.google.com/run

**Attendre 3-5 minutes** pour que le déploiement se termine.

#### Étape 12: Vérifier que l'application fonctionne en production

```bash
# Test de santé
curl https://votre-app.run.app/health

# Résultat attendu:
# {"ok":true,"status":"alive","timestamp":"2025-10-13T10:30:00+00:00"}

# Test de readiness
curl https://votre-app.run.app/ready

# Résultat attendu:
# {"ok":true,"status":"ready","credentials":{...},"timestamp":"..."}

# Test de configuration
curl https://votre-app.run.app/api/config

# Résultat attendu:
# {"ok":true,"client_id":"...","default_sheet":"...","who_options":[...]}
```

**Ou via Make:**
```bash
make smoke-test-prod
```

#### Étape 13: Tester l'application manuellement

1. Ouvrir l'URL de production dans le navigateur
2. Se connecter avec Google (email autorisé)
3. Scanner un ticket de test
4. Vérifier que les données sont bien écrites dans Google Sheets

---

### Déploiement continu (GitHub Actions)

Le workflow `.github/workflows/tests.yml` s'exécute automatiquement sur:
- Push vers `main`, `staging`, `dev`
- Pull Request vers `main`

**Étapes du workflow:**
1. Checkout du code
2. Setup PHP 8.1 + Composer
3. Setup Node.js 18
4. Installation des dépendances
5. Tests de syntaxe PHP et JavaScript
6. PHPCS (linting)
7. PHPUnit avec couverture de code
8. Upload coverage vers Codecov (optionnel)
9. Validation Docker et cloudbuild.yaml

**En cas d'échec:**
- Vérifier les logs dans l'onglet "Actions"
- Corriger les erreurs localement
- Re-pousser le fix

---

### Déploiement manuel (sans GitHub Actions)

Si vous préférez déployer manuellement:

```bash
# 1. Configurer gcloud CLI
gcloud auth login
gcloud config set project votre-project-id

# 2. Configurer les secrets GCP (première fois uniquement)
make setup-gcp-secrets
# Suivre les instructions interactives

# 3. Déployer directement sur Cloud Run
make deploy-direct
# Suivre les instructions (confirmation, tests)

# 4. Vérifier le déploiement
make smoke-test-prod
```

**Le déploiement direct:**
1. Demande confirmation
2. Build l'image via Cloud Build
3. Pousse sur Artifact Registry
4. Déploie sur Cloud Run
5. Exécute les smoke tests

---

### Configuration secrets GCP (Production)

Les secrets sont stockés dans **Google Secret Manager** et injectés automatiquement dans Cloud Run.

**Créer les secrets (première fois):**

```bash
# Via Makefile (interactif)
make setup-gcp-secrets

# Ou manuellement
gcloud secrets create oauth-client-id \
  --data-file=<(echo -n "votre-client-id") \
  --replication-policy="automatic"

gcloud secrets create spreadsheet-id \
  --data-file=<(echo -n "votre-spreadsheet-id") \
  --replication-policy="automatic"

gcloud secrets create allowed-emails \
  --data-file=<(echo -n "user1@gmail.com,user2@gmail.com") \
  --replication-policy="automatic"

# Etc. pour: gcp-project-id, gcp-processor-id, who-columns
```

**Donner accès au Service Account:**

```bash
# Récupérer l'email du SA Cloud Run
SA_EMAIL=$(gcloud run services describe votre-service \
  --platform managed --region europe-west1 \
  --format='value(spec.template.spec.serviceAccountName)')

# Donner accès aux secrets
gcloud secrets add-iam-policy-binding oauth-client-id \
  --member="serviceAccount:$SA_EMAIL" \
  --role="roles/secretmanager.secretAccessor"

# Répéter pour chaque secret
```

**Configuration dans cloudbuild.yaml:**

Les secrets sont automatiquement montés comme variables d'environnement dans Cloud Run. Voir `cloudbuild.yaml` section `env`.

---

### Rollback en cas de problème

Si le déploiement échoue ou cause des problèmes:

```bash
# 1. Lister les révisions Cloud Run
gcloud run revisions list --service=votre-service --region=europe-west1

# 2. Identifier la dernière révision stable (ex: votre-service-00042)

# 3. Revenir à cette révision
gcloud run services update-traffic votre-service \
  --to-revisions=votre-service-00042=100 \
  --region=europe-west1

# 4. Vérifier
make smoke-test-prod
```

**Ou rollback Git complet:**

```bash
# 1. Identifier le dernier commit stable
git log --oneline

# 2. Revenir à ce commit
git reset --hard <commit-sha>

# 3. Force push (ATTENTION: destructif)
git push origin main --force

# GitHub Actions va redéployer automatiquement
```

---

## 📚 API Endpoints

### Endpoints publics (pas d'auth)

#### `GET /api/config`
Récupère la configuration de l'application.

**Réponse:**
```json
{
  "ok": true,
  "client_id": "123-abc.apps.googleusercontent.com",
  "default_sheet": "Dépenses 2025",
  "receipt_api_url": "https://votre-app.run.app/api/scan",
  "who_options": ["Sabrina", "Mickael"],
  "max_batch": 10
}
```

#### `GET /health`
Health check pour liveness probe.

**Réponse:**
```json
{
  "ok": true,
  "status": "alive",
  "timestamp": "2025-10-13T10:30:00+00:00"
}
```

#### `GET /ready`
Readiness check avec validation des credentials.

**Réponse:**
```json
{
  "ok": true,
  "status": "ready",
  "credentials": {
    "valid": true,
    "project_id": "my-project-123456",
    "client_email": "sa@my-project.iam.gserviceaccount.com"
  },
  "timestamp": "2025-10-13T10:30:00+00:00"
}
```

---

### Endpoints protégés (requiert Authorization header)

Tous les endpoints protégés nécessitent:
```
Authorization: Bearer <google-oauth-token>
```

#### `GET /api/auth/me`
Vérifie l'authentification de l'utilisateur.

**Réponse:**
```json
{
  "ok": true,
  "email": "user@gmail.com"
}
```

**Erreurs:**
- `401` - Token manquant ou invalide
- `403` - Email non autorisé

#### `GET /api/sheets`
Liste les feuilles du Spreadsheet.

**Réponse:**
```json
{
  "ok": true,
  "sheets": [
    {"sheetId": 0, "title": "Dépenses 2025", "index": 0},
    {"sheetId": 1, "title": "Archive 2024", "index": 1}
  ],
  "default_sheet": "Dépenses 2025"
}
```

#### `POST /api/scan`
Scanne un ticket et extrait les données.

**Body (JSON):**
```json
{
  "imageBase64": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Réponse:**
```json
{
  "ok": true,
  "supplier_name": "Carrefour",
  "receipt_date": "2025-01-15",
  "total_amount": 25.50
}
```

**Query params optionnels:**
- `?raw=1` - Retourne la réponse brute Document AI (debug)

#### `POST /api/scan/batch`
Scanne plusieurs tickets en batch (max 10).

**Body (JSON):**
```json
{
  "imagesBase64": [
    "data:image/jpeg;base64,...",
    "data:image/jpeg;base64,...",
    "data:image/jpeg;base64,..."
  ]
}
```

**Réponse:**
```json
{
  "ok": true,
  "items": [
    {
      "ok": true,
      "supplier_name": "Carrefour",
      "receipt_date": "2025-01-15",
      "total_amount": 25.50
    },
    {
      "ok": true,
      "supplier_name": "Auchan",
      "receipt_date": "2025-01-16",
      "total_amount": 42.30
    }
  ]
}
```

#### `POST /api/sheets/write`
Écrit une entrée dans Google Sheets.

**Body (JSON):**
```json
{
  "sheetName": "Dépenses 2025",
  "who": "Mickael",
  "supplier": "Carrefour",
  "dateISO": "2025-01-15",
  "total": 25.50
}
```

**Réponse:**
```json
{
  "ok": true,
  "written": {
    "row": 15,
    "attempts": 1
  }
}
```

---

### Endpoints de debug (APP_ENV=local uniquement)

#### `GET /debug/routes`
Liste toutes les routes disponibles avec descriptions.

#### `GET /api/debug/headers`
Affiche les headers HTTP reçus (debug auth).

---

### Rate Limiting

Tous les endpoints `/api/*` sont soumis à rate limiting:

| Endpoint | Limite | Fenêtre |
|----------|--------|---------|
| `/api/scan` | 10 requêtes | 60 secondes |
| `/api/scan/batch` | 5 requêtes | 60 secondes |
| `/api/sheets/write` | 20 requêtes | 60 secondes |
| Autres `/api/*` | 30 requêtes | 60 secondes |

**En cas de dépassement:**
```
HTTP 429 Too Many Requests
Retry-After: 42

{"error":"Rate limit exceeded. Try again in 42 seconds."}
```

---

## 🛠️ Développement

### Workflow de développement

1. **Créer une branche de feature:**
   ```bash
   git checkout -b feat/nouvelle-fonctionnalite
   ```

2. **Développer avec hot reload:**
   ```bash
   make up
   # Éditer les fichiers PHP/JS/HTML
   # Rafraîchir le navigateur → changements appliqués
   ```

3. **Ajouter des tests:**
   ```bash
   # Créer un nouveau test
   nano backend/tests/Unit/MaNouvelleFonctionTest.php

   # Exécuter les tests
   make test
   ```

4. **Vérifier la qualité:**
   ```bash
   make lint          # Linting
   make format        # Auto-formatter
   make test-coverage # Couverture
   ```

5. **Commiter et pusher:**
   ```bash
   git add .
   git commit -m "feat: ajouter nouvelle fonctionnalité"
   git push origin feat/nouvelle-fonctionnalite
   ```

6. **Créer une Pull Request sur GitHub**

---

### Ajouter un nouveau endpoint

**Exemple: Ajouter `GET /api/receipts/history`**

1. **Ajouter la fonction métier dans `backend/app.php`:**
   ```php
   function getReceiptsHistory(string $who, int $limit = 10): array
   {
       // Logique métier (pure function)
       return [];
   }
   ```

2. **Ajouter le handler dans `backend/index.php`:**
   ```php
   if ($path === '/api/receipts/history' && $_SERVER['REQUEST_METHOD'] === 'GET') {
       try {
           requireGoogleUserAllowed($ALLOWED_EMAILS, $CLIENT_ID);
           $who = $_GET['who'] ?? '';
           $limit = (int)($_GET['limit'] ?? 10);

           $history = getReceiptsHistory($who, $limit);
           sendJsonResponse(['ok' => true, 'history' => $history]);
       } catch (Throwable $e) {
           sendErrorResponse($e->getMessage(), 500);
       }
   }
   ```

3. **Ajouter la route dans la liste `/debug/routes`:**
   ```php
   'GET /api/receipts/history' => 'api.php::receipts/history - Historique des tickets'
   ```

4. **Créer les tests dans `backend/tests/Unit/ReceiptsHistoryTest.php`:**
   ```php
   public function testGetReceiptsHistory(): void
   {
       $result = getReceiptsHistory('Mickael', 5);
       $this->assertIsArray($result);
       $this->assertLessThanOrEqual(5, count($result));
   }
   ```

5. **Tester:**
   ```bash
   make test
   curl http://localhost:8080/api/receipts/history?who=Mickael&limit=5
   ```

---

### Structure du code (architecture)

**Séparation stricte en 3 fichiers:**

1. **`bootstrap.php`** - Side effects uniquement
   - Validation variables d'environnement
   - `require` des dépendances
   - Aucune fonction définie ici

2. **`app.php`** - Déclarations uniquement
   - Toutes les fonctions métier
   - Fonctions pures (pas d'exécution)
   - Aucun `exit()`, `header()`, `echo` sauf dans sendJsonResponse/sendErrorResponse

3. **`index.php`** - Runtime logic uniquement
   - Router
   - Handlers de requêtes
   - Utilise les fonctions de `app.php`

**Pourquoi cette architecture?**
- Évite "Cannot redeclare function" errors
- Facilite les tests unitaires (require app.php sans side effects)
- Séparation claire entre déclarations et exécution

---

### Debugging

**Mode debug local:**
```bash
# Activer le mode debug dans .env
DEBUG=1
APP_ENV=local

# Redémarrer l'app
make restart

# Les logs incluront maintenant des dumps détaillés
make logs
```

**Fonctions de debug disponibles:**
```php
// Dans index.php
debugDump($data, 'Label');  // Dump une variable

// Les dumps s'affichent:
// - En JSON dans les réponses API (commentaires)
// - En HTML dans les pages web
```

**Endpoints de debug:**
- `/debug/routes` - Liste des routes
- `/api/debug/headers` - Headers HTTP reçus

**Logs structurés:**
```php
logMessage('info', 'Mon message', ['key' => 'value']);
// Sortie: [2025-10-13T10:30:00+00:00] {"level":"info","message":"Mon message","key":"value"}
```

---

## 🐛 Troubleshooting

### Problème: "Container failed to start" sur Cloud Run

**Symptômes:**
- Déploiement échoue avec timeout
- Logs: "Failed to start and then listen on the port defined by the PORT environment variable"

**Causes:**
- Apache ne démarre pas à temps (timeout < 300s)
- Port 8080 non bind correctement

**Solutions:**
1. Vérifier le script de démarrage `infra/docker/start.sh`
2. Augmenter le timeout dans `cloudbuild.yaml` (déjà à 300s)
3. Vérifier les logs Cloud Run:
   ```bash
   gcloud logging read "resource.type=cloud_run_revision" --limit=50
   ```

---

### Problème: "Token Google manquant" (401)

**Symptômes:**
- Erreur 401 lors d'appels API protégés
- Message: "Connexion non autorisée"

**Causes:**
- Token OAuth non envoyé dans le header `Authorization`
- Token expiré (validité: 1 heure)

**Solutions:**
1. Vérifier que le frontend envoie le token:
   ```javascript
   headers: {
       'Authorization': `Bearer ${googleToken}`
   }
   ```

2. Renouveler le token Google:
   ```javascript
   // Dans app.js
   google.accounts.oauth2.revoke(accessToken);
   // Re-login
   ```

3. Débugger les headers:
   ```bash
   curl http://localhost:8080/api/debug/headers \
     -H "Authorization: Bearer mon-token"
   ```

---

### Problème: "Email non autorisé" (403)

**Symptômes:**
- Erreur 403 après login Google
- Message: "Email non autorisé"

**Causes:**
- Email non dans `ALLOWED_EMAILS`
- Case sensitivity (ALLOWED_EMAILS normalise en lowercase)

**Solutions:**
1. Vérifier la configuration:
   ```bash
   # Local
   grep ALLOWED_EMAILS infra/.env

   # Production
   gcloud secrets versions access latest --secret=allowed-emails
   ```

2. Ajouter l'email (lowercase):
   ```bash
   # Local: éditer infra/.env
   ALLOWED_EMAILS=user1@gmail.com,newuser@gmail.com

   # Production: mettre à jour le secret
   echo -n "user1@gmail.com,newuser@gmail.com" | \
     gcloud secrets versions add allowed-emails --data-file=-
   ```

3. Redémarrer l'app:
   ```bash
   # Local
   make restart

   # Production: redéployer
   gcloud run services update votre-service --region=europe-west1
   ```

---

### Problème: Tests échouent localement

**Symptômes:**
- `make test` retourne des erreurs
- Tests qui passaient avant échouent maintenant

**Causes courantes:**
1. Dépendances Composer manquantes
2. Extension PHP PCOV non installée
3. Fichiers temporaires de tests précédents

**Solutions:**
```bash
# 1. Réinstaller les dépendances
cd backend && composer install

# 2. Nettoyer le cache PHPUnit
rm -rf backend/.phpunit.result.cache

# 3. Nettoyer les fichiers temporaires
rm -rf /tmp/docai_cache_*
rm -rf /tmp/test_rate_limit_*

# 4. Relancer les tests
make test

# 5. Si PCOV manque:
# Installer dans le container
docker exec receipt-api-app pecl install pcov
docker exec receipt-api-app docker-php-ext-enable pcov
make restart
```

---

### Problème: Rate limit trop restrictif

**Symptômes:**
- Erreur 429 "Rate limit exceeded" fréquente
- Message: "Try again in X seconds"

**Solutions:**

1. **Temporairement désactiver (dev uniquement):**
   Commenter le rate limiting dans `backend/index.php`:
   ```php
   // if (str_starts_with($path, '/api/')) {
   //     $rateLimitId = getRateLimitIdentifier();
   //     applyRateLimit($rateLimitId, $path);
   // }
   ```

2. **Augmenter les limites (production):**
   Éditer `backend/rate_limit_middleware.php`:
   ```php
   const RATE_LIMITS = [
       '/api/scan' => ['requests' => 20, 'window' => 60], // 20 au lieu de 10
       '/api/scan/batch' => ['requests' => 10, 'window' => 60],
       // ...
   ];
   ```

3. **Nettoyer les états de rate limit (dev):**
   ```bash
   rm -rf /tmp/rate_limit_state_*
   ```

---

### Problème: Document AI coûte trop cher

**Symptômes:**
- Facture GCP élevée
- Quota Document AI dépassé

**Solutions:**

1. **Vérifier le cache fonctionne:**
   ```bash
   # Logs doivent montrer "Cache hit" pour les scans répétés
   make logs | grep "docai_process_bytes_cached"
   ```

2. **Nettoyer le cache ancien:**
   Le cleanup automatique s'exécute toutes les 24h.
   Forcer manuellement:
   ```php
   cleanupDocAiCache(86400); // Nettoyer fichiers >24h
   ```

3. **Réduire la qualité des images:**
   Dans `frontend/assets/js/app.js`, réduire la résolution:
   ```javascript
   canvas.width = 800;  // Au lieu de 1200
   canvas.height = 600; // Au lieu de 900
   ```

4. **Monitorer les quotas:**
   ```bash
   gcloud logging read "resource.type=documentai.processor" \
     --limit=100 --format=json | jq '.[] | .timestamp'
   ```

---

### Problème: Google Sheets écriture échoue

**Symptômes:**
- Erreur lors de `/api/sheets/write`
- Timeout ou "Lock acquisition failed"

**Causes:**
- Conflit de concurrence (plusieurs écritures simultanées)
- Service Account n'a pas les permissions
- Spreadsheet ID invalide

**Solutions:**

1. **Vérifier les permissions du SA:**
   - Ouvrir le Google Sheet
   - Partager avec l'email du Service Account (éditeur)
   - Email format: `sa-name@project-id.iam.gserviceaccount.com`

2. **Augmenter le nombre de retries:**
   Dans `backend/index.php`, ajuster:
   ```php
   $result = writeToSheetOptimistic(
       /* ... */,
       10 // Augmenter de 5 à 10 retries
   );
   ```

3. **Vérifier le Spreadsheet ID:**
   ```bash
   # Extraire l'ID de l'URL
   # https://docs.google.com/spreadsheets/d/<ID>/edit

   # Tester l'accès
   curl "https://sheets.googleapis.com/v4/spreadsheets/<ID>" \
     -H "Authorization: Bearer $(gcloud auth print-access-token)"
   ```

---

## 📄 Licence

Ce projet est sous licence MIT. Voir [LICENSE](LICENSE) pour plus de détails.

---

## 🤝 Contribution

Les contributions sont bienvenues! Pour contribuer:

1. Fork le repository
2. Créer une branche de feature: `git checkout -b feat/ma-fonctionnalite`
3. Commiter les changements: `git commit -m "feat: ajouter ma fonctionnalité"`
4. Pusher la branche: `git push origin feat/ma-fonctionnalite`
5. Créer une Pull Request

**Avant de soumettre une PR:**
- [ ] Tests passent: `make test`
- [ ] Code linté: `make lint`
- [ ] Couverture maintenue ou améliorée: `make test-coverage-text`
- [ ] Documentation mise à jour (README.md, RECAP.md)
- [ ] Git hooks installés: `make install-hooks`

---

## 📞 Support

- **Issues:** [GitHub Issues](https://github.com/votre-username/receipt-api-local-google-parser/issues)
- **Discussions:** [GitHub Discussions](https://github.com/votre-username/receipt-api-local-google-parser/discussions)
- **Email:** votre-email@example.com

---

## 📊 Statistiques du projet

- **Langage:** PHP 8.1, JavaScript ES6+
- **Tests:** 128 tests unitaires, 349 assertions
- **Couverture:** 49.58% (353/712 lignes)
- **Lignes de code:** ~2,000 lignes (backend + frontend)
- **Dépendances:** Composer (PHPUnit, PHPCS), Bootstrap 5
- **Déploiement:** Docker, Cloud Build, Cloud Run
- **Coût estimé:** <$15/mois (usage léger)

---

**Dernière mise à jour:** 13 Octobre 2025
**Version:** 1.0.0
**Maintenu par:** Votre Nom

---

🎉 **Merci d'utiliser Receipt Scanner API!**
