# 🧾 Receipt API - Scanner de Tickets avec Google Sheets

Application web moderne pour scanner des tickets de caisse et les enregistrer automatiquement dans Google Sheets avec authentification Google OAuth et traitement par Document AI.

## 📋 Table des matières

- [Quick Start](#-quick-start)
- [Architecture](#-architecture)
- [Fonctionnalités](#-fonctionnalités)
- [Développement Local](#-développement-local)
- [Configuration](#-configuration)
- [Déploiement](#-déploiement)
- [Sécurité](#-sécurité)
- [Git Hooks](#-git-hooks)
- [Monitoring](#-monitoring)
- [Troubleshooting](#-troubleshooting)
- [Support](#-support)

## ⚡ Quick Start

### 🚀 Déployer en 3 étapes

#### 1️⃣ **Configurer les secrets (une seule fois)**

```bash
make setup-gcp-secrets
```

Puis configurer `GCP_SA_KEY` dans GitHub :
- Aller sur : `https://github.com/[votre-repo]/settings/secrets/actions`
- Créer : `GCP_SA_KEY` = contenu de `backend/keys/sa-key.json`

#### 2️⃣ **Push sur staging**

```bash
git add .
git commit -m "feat: mes changements"
git push origin staging
```

**→ Déploiement automatique sur staging !** ✨

#### 3️⃣ **Push sur main (production)**

```bash
git checkout main
git merge staging
git push origin main
```

**→ Aller sur GitHub Actions et approuver le déploiement** ✅

### 📋 Commandes essentielles

```bash
# Développement
make up              # Démarrer localement
make smoke-test      # Tester

# Déploiement = Push sur GitHub
git push origin staging    # → Staging automatique
git push origin main       # → Production (avec approbation)

# Vérification
make smoke-test-staging    # Tester staging
make smoke-test-prod       # Tester production
```

## 🏗️ Architecture

### Vue d'ensemble
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend SPA  │    │   Backend PHP   │    │   Google Cloud  │
│   (Bootstrap)   │◄──►│   (Apache)      │◄──►│   Services      │
│   - Vue unique  │    │   - OAuth       │    │   - Document AI │
│   - Scan simple │    │   - API REST    │    │   - Sheets API  │
│   - Scan multi  │    │   - Validation  │    │   - OAuth       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Stack technique
- **Frontend** : HTML5, Bootstrap 5, JavaScript ES6+
- **Backend** : PHP 8.1, Apache, Composer
- **Base de données** : Google Sheets API
- **IA** : Google Document AI
- **Authentification** : Google OAuth 2.0
- **Déploiement** : Google Cloud Run
- **CI/CD** : GitHub Actions
- **Conteneurisation** : Docker

### Structure du projet
```
receipt-api-local-google-parser/
├── .github/workflows/          # CI/CD GitHub Actions
│   ├── deploy-staging.yml     # Déploiement staging
│   └── deploy-production.yml  # Déploiement production
├── .githooks/                 # Git hooks (pre-commit, pre-push)
├── backend/                   # Backend PHP
│   ├── index.php             # Point d'entrée API
│   ├── app.php               # Logique métier
│   ├── bootstrap.php         # Initialisation + validation
│   ├── keys/                 # Service Account (gitignored)
│   └── composer.json         # Dépendances PHP
├── frontend/                 # Frontend SPA
│   ├── index.html            # Interface utilisateur
│   ├── manifest.json         # PWA manifest
│   └── assets/               # CSS, JS, images
├── infra/                    # Infrastructure
│   ├── docker-compose.yml    # Environnement local
│   ├── Dockerfile            # Image Docker
│   └── apache/               # Configuration Apache
├── scripts/                  # Scripts utilitaires
│   ├── deploy-direct.sh      # Déploiement direct
│   ├── install-git-hooks.sh  # Installation des hooks
│   └── setup-gcp-secrets.sh  # Configuration des secrets
├── cloudbuild.yaml           # Cloud Build config
├── Makefile                  # Commandes de développement
├── phpcs.xml                 # Configuration PHPCS
└── .htaccess                 # Routage Apache
```

## ✨ Fonctionnalités

### 🔐 Authentification et Autorisation
- **Google OAuth 2.0** : Connexion sécurisée avec Google
- **Liste d'emails autorisés** : Contrôle d'accès par `ALLOWED_EMAILS`
- **Protection des endpoints** : Seul `/api/config` accessible sans authentification
- **Gestion des sessions** : Tokens JWT avec validation d'audience
- **Changement de compte** : Possibilité de switcher entre comptes Google

### 📱 Interface Utilisateur
- **Design responsive** : Bootstrap 5 avec thème sombre
- **Mode scan unique** : Un ticket à la fois avec aperçu
- **Mode scan multiple** : Plusieurs tickets en batch
- **Statut de connexion** : Indicateurs visuels avec points colorés
- **Monitoring des services** : État en temps réel de `/health` et `/ready`
- **Validation en temps réel** : Feedback immédiat sur les actions

### 🧾 Traitement des Tickets
- **Scan par caméra** : Capture directe depuis l'appareil
- **Upload de fichiers** : Support des formats image courants
- **Document AI** : Extraction automatique des données (marchand, date, total)
- **Validation manuelle** : Correction des données extraites
- **Enregistrement Sheets** : Sauvegarde automatique dans Google Sheets

### 🚀 Performance et Optimisation
- **Cache-busting automatique** : Invalidation du cache navigateur
- **Assets optimisés** : Minification CSS/JS en production
- **Hot reload** : Développement local avec rechargement automatique
- **Compression** : Gzip et headers de cache optimisés

## 🛠️ Développement Local

### Prérequis
- Docker et Docker Compose
- Git
- Compte Google Cloud Platform
- Service Account avec permissions Document AI et Sheets

### Installation rapide

```bash
# 1. Cloner le projet
git clone <repository-url>
cd receipt-api-local-google-parser

# 2. Configuration initiale
make install-hooks          # Installer les Git hooks (À FAIRE EN PREMIER)
make setup-gcp-secrets     # Configurer les secrets dans GCP

# 3. Configurer les variables d'environnement
# Éditer infra/.env avec vos valeurs

# 4. Placer le service account
# Copier votre sa-key.json dans backend/keys/

# 5. Démarrer l'application
make up

# 6. Tester
make smoke-test
```

### Commandes de développement

```bash
# Configuration
make setup              # Configuration initiale
make install-hooks      # Installer les Git hooks

# Docker
make up                 # Démarrer les conteneurs
make down               # Arrêter les conteneurs
make restart            # Redémarrer la stack
make logs               # Voir les logs
make ps                 # État des services
make sh-app             # Shell dans le conteneur

# Développement
make cache-bust         # Cache-busting automatique
make build-assets       # Build des assets avec hash

# Tests
make smoke-test         # Tests locaux
make smoke-test-staging # Tests staging
make smoke-test-prod    # Tests production

# Qualité de code
make lint               # Linter (JS + PHP)
make check-quality      # Vérifications complètes
make format             # Formatage automatique

# Déploiement
make deploy-direct      # Déploiement direct vers Cloud Run
make check-deployment   # Vérifier le statut du déploiement
```

### Hot Reload
Le système de hot reload est configuré pour :
- **Backend PHP** : Modifications instantanées des fichiers PHP
- **Frontend** : Rechargement automatique des assets HTML/CSS/JS
- **Variables d'environnement** : Rechargement via `make restart`

## ⚙️ Configuration

### Variables d'environnement obligatoires

Créer `infra/.env` basé sur `infra/.env.example` :

```bash
# Environnement
APP_ENV=local
DEBUG=1

# Google Cloud Configuration
GCP_PROJECT_ID=scan-document-ai
GCP_LOCATION=eu

# Google OAuth
GOOGLE_OAUTH_CLIENT_ID=your-oauth-client-id.googleusercontent.com

# Google Sheets
SPREADSHEET_ID=your-spreadsheet-id
DEFAULT_SHEET=Sheet1

# Document AI
GCP_PROCESSOR_ID=your-document-ai-processor-id

# Sécurité
ALLOWED_EMAILS=your-email@gmail.com
ALLOWED_ORIGINS=http://localhost:8080

# Configuration de l'application
WHO_COLUMNS={"Mickael":["A","B","C"],"Marie":["D","E","F"]}
MAX_BATCH_UPLOADS=10

# Credentials
GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/keys/sa-key.json
```

### Configuration Google Cloud

1. **Créer un projet GCP** et activer les APIs :
   - Document AI API
   - Google Sheets API
   - Google OAuth 2.0

2. **Créer un Service Account** avec permissions :
   - Document AI Editor
   - Google Sheets Editor

3. **Télécharger la clé JSON** et la placer dans `backend/keys/sa-key.json`

4. **Configurer OAuth** :
   - Créer des identifiants OAuth 2.0
   - Ajouter `http://localhost:8080` aux origines autorisées
   - Récupérer le Client ID

### Configuration Google Sheets

1. **Créer un Google Sheet** avec les colonnes :
   - A : Qui (nom de la personne)
   - B : Intitulé (marchand)
   - C : Date
   - D : Total

2. **Partager avec le Service Account** (email du SA)

3. **Récupérer l'ID** du spreadsheet depuis l'URL

## 🚀 Déploiement

### Workflow de déploiement complet

```
┌─────────────────────────────────────────────────────────────┐
│  1. DÉVELOPPEMENT LOCAL                                      │
│  ┌────────────────────────────────────────────────────┐    │
│  │  make up           → Démarrer l'app                │    │
│  │  make smoke-test   → Tester localement             │    │
│  │  git add .         → Ajouter les changements       │    │
│  │  git commit -m ""  → Commiter                      │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  2. DÉPLOIEMENT STAGING (automatique)                       │
│  ┌────────────────────────────────────────────────────┐    │
│  │  git push origin staging                           │    │
│  │  → GitHub Actions démarre automatiquement          │    │
│  │  → Cache-busting automatique                       │    │
│  │  → Cloud Build construit l'image                   │    │
│  │  → Cloud Run déploie en staging                    │    │
│  │  → Smoke tests automatiques                        │    │
│  │  ✅ Déploiement staging terminé                    │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  3. VALIDATION STAGING                                       │
│  ┌────────────────────────────────────────────────────┐    │
│  │  Tester l'application sur l'URL staging            │    │
│  │  Vérifier que tout fonctionne                      │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  4. DÉPLOIEMENT PRODUCTION (automatique + approbation)      │
│  ┌────────────────────────────────────────────────────┐    │
│  │  git checkout main                                 │    │
│  │  git merge staging                                 │    │
│  │  git push origin main                              │    │
│  │  → GitHub Actions démarre automatiquement          │    │
│  │  → Cache-busting automatique                       │    │
│  │  → Cloud Build construit l'image                   │    │
│  │  ⏸️  ATTENTE D'APPROBATION MANUELLE                │    │
│  │  → (Vous approuvez sur GitHub Actions)            │    │
│  │  → Cloud Run déploie en production                 │    │
│  │  → Smoke tests automatiques                        │    │
│  │  ✅ Déploiement production terminé                 │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### Configuration initiale (À faire UNE SEULE FOIS)

#### Étape 1 : Configurer les secrets dans Google Secret Manager

```bash
# Configuration automatique des secrets dans GCP
make setup-gcp-secrets
```

Vous devrez entrer :
- Client ID OAuth Google
- ID du Google Sheet
- ID du processeur Document AI
- Emails autorisés (ex: `email1@gmail.com,email2@gmail.com`)
- WHO_COLUMNS JSON (ex: `{"Mickael":["A","B","C"],"Marie":["D","E","F"]}`)

#### Étape 2 : Configurer le secret GitHub (Service Account)

**Seul secret requis dans GitHub :**

1. Aller sur GitHub : `https://github.com/[votre-repo]/settings/secrets/actions`

2. Créer un nouveau secret :
   - **Nom** : `GCP_SA_KEY`
   - **Valeur** : Contenu complet du fichier `backend/keys/sa-key.json`

#### Étape 3 : Vérifier la configuration

```bash
# Vérifier que l'app fonctionne localement
make up
make smoke-test

# Vérifier les secrets dans GCP
gcloud secrets list --project=scan-document-ai
```

### Déploiement Direct (Alternative)

```bash
# 1. Faire vos modifications
# ... éditer le code ...

# 2. Commit
git add .
git commit -m "feat: vos changements"
git push origin main

# 3. Déployer directement vers Cloud Run
make deploy-direct
```

**Ce qui se passe :**
1. ✅ Cache-busting automatique
2. ✅ Confirmation avant déploiement
3. ✅ Build Docker via Cloud Build
4. ✅ Push vers Artifact Registry
5. ✅ Déploiement sur Cloud Run
6. ✅ Tests automatiques après déploiement

### Cache-busting automatique

Le système de cache-busting automatique garantit que les utilisateurs reçoivent toujours les dernières versions des assets CSS/JS.

#### Commandes disponibles

```bash
# Cache-busting simple
make cache-bust

# Déploiement avec cache-busting automatique
make deploy-staging    # Déploiement vers staging
make deploy-prod       # Déploiement vers production
```

#### Workflow CI/CD

Le cache-busting est automatiquement intégré dans les workflows GitHub Actions :

1. **Deploy Staging** : Cache-busting automatique avant déploiement
2. **Deploy Production** : Cache-busting automatique avant déploiement

#### Déclenchement automatique

- **Modification des assets** (`frontend/assets/**`) → Cache-busting automatique
- **Push sur staging** → Cache-busting + déploiement
- **Push sur main** → Cache-busting + déploiement (avec approbation)

## 🔒 Sécurité

### Architecture de sécurité

```
┌─────────────────────────────────────────────────────────┐
│  Développement Local                                     │
│  ┌─────────────────┐                                    │
│  │  infra/.env     │ ← Fichier local (gitignored)      │
│  │  backend/keys/  │ ← Service Account (gitignored)    │
│  └─────────────────┘                                    │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│  Production (Google Cloud)                               │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Google Secret Manager                            │  │
│  │  ├── oauth-client-id          (chiffré)          │  │
│  │  ├── spreadsheet-id           (chiffré)          │  │
│  │  ├── gcp-project-id           (chiffré)          │  │
│  │  ├── gcp-processor-id         (chiffré)          │  │
│  │  ├── allowed-emails           (chiffré)          │  │
│  │  └── who-columns              (chiffré)          │  │
│  └──────────────────────────────────────────────────┘  │
│                        ↓                                 │
│  ┌──────────────────────────────────────────────────┐  │
│  │  Cloud Run (avec Service Account IAM)            │  │
│  │  └── Variables d'environnement injectées         │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Bonnes pratiques de sécurité implémentées

#### ❌ **Ce qu'il NE FAUT JAMAIS faire :**
```javascript
// ❌ MAUVAIS - Secret en dur dans le code
const API_KEY = "AIzaSyC_abc123_SECRET_KEY";
const SPREADSHEET = "1abc_123_secret_spreadsheet_id";
```

#### ✅ **Ce que nous faisons (CORRECT) :**

```
📦 Secrets stockés dans Google Secret Manager (GCP)
    ↓
🔒 Chiffrés et gérés par Google
    ↓
🔐 Accessibles uniquement par Cloud Run (via IAM)
    ↓
⚡ Injectés comme variables d'environnement au runtime
```

### Niveaux de sécurité

| Niveau | Méthode | Sécurité | Recommandation |
|--------|---------|----------|----------------|
| 🔴 **Danger** | Secrets en dur dans le code | ❌ Très faible | JAMAIS |
| 🟡 **Moyen** | GitHub Secrets | ⚠️ Moyenne | Acceptable pour CI/CD uniquement |
| 🟢 **Excellent** | **Google Secret Manager** | ✅ Très élevée | **RECOMMANDÉ** |

### Sécurité par couche

#### 1. Authentification
- ✅ Google OAuth 2.0
- ✅ Validation des tokens
- ✅ Vérification de l'audience
- ✅ Liste d'emails autorisés

#### 2. Autorisation
- ✅ Service Account avec permissions minimales
- ✅ IAM roles strictes
- ✅ Protection des endpoints API
- ✅ CORS configuré

#### 3. Secrets
- ✅ Google Secret Manager (chiffrement au repos)
- ✅ Transmission sécurisée (TLS)
- ✅ Pas de secrets dans le code
- ✅ Pas de secrets dans les logs
- ✅ Rotation possible

#### 4. Infrastructure
- ✅ Cloud Run (isolation des conteneurs)
- ✅ VPC si nécessaire
- ✅ HTTPS obligatoire
- ✅ Firewall configuré

### Permissions IAM recommandées

Service Account `docai-sa@scan-document-ai.iam.gserviceaccount.com`

**Permissions minimales requises :**
```yaml
roles/secretmanager.secretAccessor  # Accès aux secrets
roles/documentai.apiUser             # Document AI
roles/sheets.editor                   # Google Sheets
```

### Checklist de sécurité

#### Avant de déployer en production :
- [ ] Tous les secrets dans Google Secret Manager
- [ ] Aucun fichier `.env` ou `sa-key.json` dans Git
- [ ] `.gitignore` à jour
- [ ] Permissions IAM configurées
- [ ] CORS correctement configuré
- [ ] Liste d'emails autorisés à jour
- [ ] HTTPS activé sur Cloud Run
- [ ] Logs sécurisés (pas de secrets exposés)

#### Fichiers à NE JAMAIS commiter :
```
# .gitignore
backend/keys/sa-key.json    # Service Account
infra/.env                  # Variables d'environnement
*.pem
*.key
*.p12
*credentials*.json
```

## 🪝 Git Hooks

### Qu'est-ce qu'un Git Hook ?

Les Git hooks sont des scripts qui s'exécutent automatiquement à certains moments du workflow Git (commit, push, etc.). Ils permettent de :

- ✅ Vérifier la qualité du code avant commit
- ✅ Empêcher le commit de secrets
- ✅ Demander confirmation avant push vers production
- ✅ Bloquer les déploiements accidentels

### Installation

```bash
make install-hooks
```

Ou manuellement :
```bash
./scripts/install-git-hooks.sh
```

### Pre-commit Hook

Exécuté **avant chaque commit**, vérifie :

#### ✅ Vérifications PHP
- Syntaxe PHP valide (`php -l`)
- PHPCS (si configuré) pour le respect des standards
- Pas de fichiers `backend/keys/*.json` commités

#### ✅ Vérifications JavaScript
- Syntaxe JavaScript valide
- Avertissement sur les `console.log`

#### ✅ Vérifications de sécurité
- Aucune clé API (pattern `sk-...`, `AIza...`)
- Aucun fichier `.env` commité
- Aucun mot de passe en clair
- Pas de fichiers de clés dans `backend/keys/`

#### ✅ Vérifications de structure
- Pas de fichiers > 1MB (sauf images)
- Structure de fichiers correcte

### Pre-push Hook

Exécuté **avant chaque push**, vérifie :

#### ✅ Tests PHPCS
```bash
./backend/vendor/bin/phpcs --standard=phpcs.xml backend/
```

#### ✅ Dépendances
- `composer.lock` à jour si `composer.json` modifié

#### ✅ Configuration Docker
- `Dockerfile` valide
- `.htaccess` copié dans l'image

#### ✅ Cloud Build
- `cloudbuild.yaml` valide (YAML)

#### ⚠️ Confirmation pour push vers `main`
Si vous pushez vers `main`, le hook :
1. Affiche un avertissement (déploiement Cloud Run)
2. Demande confirmation (y/N)
3. Propose de lancer `make smoke-test`

**Exemple :**
```
╔════════════════════════════════════════════════════════╗
║  ⚠️  ATTENTION: Push vers MAIN                        ║
║                                                        ║
║  Cela va déclencher le déploiement sur Cloud Run !   ║
║                                                        ║
║  Assurez-vous que:                                    ║
║  • Les tests locaux passent                           ║
║  • Le code a été testé en local                       ║
║  • make smoke-test fonctionne                         ║
╚════════════════════════════════════════════════════════╝

Voulez-vous vraiment déployer en production ? (y/N)
```

### Bypasser les hooks (déconseillé)

Si vraiment nécessaire :

```bash
# Bypasser pre-commit
git commit --no-verify -m "message"

# Bypasser pre-push
git push --no-verify origin main
```

**⚠️ ATTENTION :** Cela peut entraîner :
- Commit de secrets
- Déploiement de code cassé
- Erreurs de syntaxe en production

### Tester les hooks

#### Tester pre-commit

```bash
# Créer un fichier avec une erreur de syntaxe
echo "<?php echo 'test'" > backend/test.php

# Tenter de commit
git add backend/test.php
git commit -m "test"

# Le hook devrait bloquer le commit
```

#### Tester pre-push

```bash
# Modifier un fichier
echo "// test" >> backend/index.php
git add backend/index.php
git commit -m "test"

# Tenter de push vers main
git push origin main

# Le hook devrait demander confirmation
```

### Avantages

- ✅ **Sécurité** : Empêche le commit de secrets
- ✅ **Qualité** : Code vérifié avant commit
- ✅ **Confiance** : Confirmation avant déploiement
- ✅ **Rapidité** : Détection des erreurs avant CI/CD
- ✅ **Économie** : Moins de builds GitHub Actions

## 📊 Monitoring et Observabilité

### Endpoints de santé

#### `/health` - Liveness Probe
- **Méthode** : GET
- **Authentification** : Non requise
- **Réponse** : Statut de l'application (en vie)

```json
{
  "ok": true,
  "status": "alive",
  "timestamp": "2025-10-07T23:34:57+00:00"
}
```

#### `/ready` - Readiness Probe
- **Méthode** : GET
- **Authentification** : Non requise
- **Réponse** : Statut des services (prêt)

```json
{
  "ok": true,
  "status": "ready",
  "credentials": {
    "valid": true,
    "project_id": "scan-document-ai",
    "client_email": "docai-sa@scan-document-ai.iam.gserviceaccount.com"
  },
  "timestamp": "2025-10-07T23:34:57+00:00"
}
```

### Monitoring frontend

L'interface affiche en temps réel l'état des services :
- **🟢 ● App** : Application en vie
- **🟢 ● Services** : Services opérationnels (PHP + DocAI)
- **🔴 ● Error** : Service en erreur
- **🔴 ● Offline** : Service inaccessible

### Logging structuré

Tous les logs sont au format JSON avec :
- Timestamp ISO 8601
- Niveau de log (info, warn, error)
- Message descriptif
- Contexte (endpoint, méthode, user-agent)
- Données spécifiques à l'événement

### Métriques Cloud Run

- **Requêtes par seconde**
- **Latence de réponse**
- **Taux d'erreur**
- **Utilisation CPU/Mémoire**
- **Durée d'exécution**

## 🔧 Troubleshooting

### Problèmes de déploiement Cloud Run

#### Erreur : "Container failed to start and listen on the port"

**Symptôme complet :**
```
ERROR: (gcloud.run.deploy) Revision 'receipt-parser-xxx' is not ready and cannot serve traffic. 
The user-provided container failed to start and listen on the port defined provided by the 
PORT=8080 environment variable within the allocated timeout.
```

**Solutions appliquées :**

1. **Amélioration du script de démarrage (`infra/docker/start.sh`)**
2. **Augmentation du timeout Cloud Run (`cloudbuild.yaml`)**
3. **Optimisation du Dockerfile**
4. **Création d'un `.dockerignore`**

**Vérification après déploiement :**

```bash
# Vérifier les logs de démarrage
gcloud logging read "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser" \
  --project=scan-document-ai \
  --limit=100 \
  --format="table(timestamp,textPayload)"

# Rechercher les erreurs
gcloud logging read "resource.type=cloud_run_revision AND 
  severity>=ERROR" \
  --project=scan-document-ai \
  --limit=50
```

### Problèmes de secrets

#### Erreur : "Secret was not found"

**Symptôme :**
```
ERROR: spec.template.spec.containers[0].env[9].value_from.secret_key_ref.name: 
Secret projects/264113083582/secrets/allowed-emails/versions/latest was not found
```

**Solution :**
```bash
# Créer les secrets manquants
make setup-gcp-secrets

# Ou via Cloud Shell
echo -n "polmickael3@gmail.com" | gcloud secrets create allowed-emails \
  --data-file=- \
  --replication-policy="automatic" \
  --project=scan-document-ai
```

### Problèmes de routage (403, 404)

#### Erreur 403 Forbidden sur `/`

**Cause :** `.htaccess` non copié dans l'image Docker ou Apache ne lit pas le `.htaccess`

**Solution :**
1. Vérifier que `.htaccess` est copié dans le Dockerfile
2. Vérifier qu'Apache autorise `.htaccess`
3. Vérifier que `mod_rewrite` est activé

#### Erreur 404 sur `/api/config`

**Cause :** Routage `.htaccess` incorrect ou `index.php` manquant

**Solution :**
Vérifier les règles de réécriture dans `.htaccess`

### Smoke tests échouent

#### Erreur : "gcloud: command not found" dans smoke tests

**Cause :** Utilisation de l'image `gcr.io/cloud-builders/curl` qui ne contient pas `gcloud`

**Solution :**
```yaml
# cloudbuild.yaml
- name: 'gcr.io/cloud-builders/gcloud'  # ← Pas 'curl'
  id: 'smoke-tests'
```

### Commandes de diagnostic

#### Vérifier l'état du service Cloud Run
```bash
make check-deployment

# Ou manuellement
gcloud run services describe receipt-parser \
  --region=europe-west9 \
  --project=scan-document-ai
```

#### Voir les logs en temps réel
```bash
gcloud logging tail "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser" \
  --project=scan-document-ai
```

#### Voir les dernières erreurs
```bash
gcloud logging read "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser AND 
  severity>=ERROR" \
  --project=scan-document-ai \
  --limit=50 \
  --format="table(timestamp,severity,textPayload)"
```

#### Tester le conteneur localement
```bash
# Build l'image
cd infra
docker build -t receipt-parser-test -f Dockerfile ..

# Lancer le conteneur
docker run --rm -p 8080:8080 \
  -e PORT=8080 \
  -e APP_ENV=local \
  receipt-parser-test

# Tester
curl http://localhost:8080/
curl http://localhost:8080/api/config
```

### Checklist de diagnostic

Quand un déploiement échoue, suivez cette checklist :

- [ ] **Vérifier les logs Cloud Build**
- [ ] **Vérifier que l'image est bien créée**
- [ ] **Vérifier les secrets**
- [ ] **Vérifier les permissions du Service Account**
- [ ] **Tester localement**
- [ ] **Vérifier les logs de démarrage Cloud Run**

## 📞 Support

### Contacts
- **Email** : polmickael3@gmail.com
- **Logs** : Cloud Logging (GCP Console)
- **Monitoring** : Cloud Run metrics

### Ressources utiles
- **Documentation Google Cloud** : https://cloud.google.com/docs
- **Documentation Document AI** : https://cloud.google.com/document-ai/docs
- **Documentation Sheets API** : https://developers.google.com/sheets/api

### Checklist de déploiement

#### Avant le déploiement
- [ ] Tests locaux passent (`make smoke-test`)
- [ ] Variables d'environnement configurées
- [ ] Service account avec bonnes permissions
- [ ] Google Sheet partagé avec le service account

#### Pendant le déploiement
- [ ] Workflow GitHub Actions en cours
- [ ] Cache-busting appliqué automatiquement
- [ ] Build Docker réussi
- [ ] Déploiement Cloud Run réussi

#### Après le déploiement
- [ ] Smoke tests passent
- [ ] Interface utilisateur accessible
- [ ] Authentification Google fonctionne
- [ ] Scan et enregistrement fonctionnent
- [ ] Monitoring des services opérationnel

---

## 📝 Changelog

### Version actuelle
- ✅ Système de cache-busting automatique
- ✅ CI/CD pipeline complet avec GitHub Actions
- ✅ Monitoring des services en temps réel
- ✅ Interface utilisateur améliorée avec indicateurs de statut
- ✅ Sécurité renforcée avec validation stricte
- ✅ Documentation complète et détaillée
- ✅ Hot reload pour le développement local
- ✅ Scripts de déploiement automatisés
- ✅ Git hooks pour la qualité de code
- ✅ Gestion sécurisée des secrets avec Google Secret Manager

### Prochaines améliorations
- 🔄 Dashboard d'administration
- 🔄 Analytics d'utilisation
- 🔄 Support multi-langues
- 🔄 API webhooks pour intégrations

---

## 🎉 Configuration Complète

### Ce qui a été mis en place

#### 🔐 Sécurité
- ✅ **Git Hooks** - Empêchent le commit de secrets et de code cassé
- ✅ **Google Secret Manager** - Gestion sécurisée des secrets
- ✅ **Service Account** - Authentification Cloud Run
- ✅ **Emails autorisés** - Liste blanche des utilisateurs

#### 🚀 Déploiement
- ✅ **Déploiement direct** - `make deploy-direct` sans GitHub Actions
- ✅ **Cache-busting** - Automatique avant chaque déploiement  
- ✅ **Cloud Build** - Build et déploiement sur GCP
- ✅ **Health checks** - `/health` et `/ready` endpoints

#### 🧪 Qualité de code
- ✅ **Pre-commit hook** - Vérifie syntaxe PHP/JS avant commit
- ✅ **Pre-push hook** - Demande confirmation avant push vers main
- ✅ **PHPCS** - Standards de code PHP
- ✅ **Smoke tests** - Tests automatiques après déploiement

#### 🎨 Frontend
- ✅ **PWA** - Progressive Web App avec manifest
- ✅ **Service monitoring** - Surveillance des endpoints
- ✅ **Multi-scan** - Support batch avec progression
- ✅ **Cache-busting** - Assets versionnés

#### 🔧 Backend
- ✅ **PHP 8.1** - Version moderne
- ✅ **Composer** - Gestion des dépendances
- ✅ **Google APIs** - Sheets + Document AI
- ✅ **Logging** - Logs structurés JSON
- ✅ **HTTPS detection** - Support Cloud Run

### Workflow de développement

```
1. Installer les hooks (une seule fois)
   make install-hooks

2. Développer et tester localement
   make up
   make smoke-test

3. Commiter (hooks vérifient automatiquement)
   git add .
   git commit -m "feat: mes changements"

4. Push (confirmation demandée pour main)
   git push origin main
   
5. Déployer (quand vous voulez)
   make deploy-direct
```

### Points importants

#### 🚫 NE JAMAIS faire
- ❌ Commit de `backend/keys/*.json`
- ❌ Commit de fichiers `.env`
- ❌ Push vers main sans confirmation
- ❌ Bypasser les hooks sans raison (`--no-verify`)

#### ✅ TOUJOURS faire
- ✅ `make install-hooks` après chaque `git clone`
- ✅ `make smoke-test` avant déploiement
- ✅ Vérifier les logs après déploiement
- ✅ Tester en local avant push

---

**En résumé : Aucun secret n'est stocké dans GitHub, ni dans le code. Tout est sécurisé dans Google Secret Manager.** ✅🔐

**Le processus est maintenant complètement automatisé :**

1. **Vous codez** → `git add` + `git commit`
2. **Vous pushez** → `git push origin staging` ou `git push origin main`
3. **Le reste est automatique** → Cloud Build déploie sur Cloud Run

**Pas de configuration complexe, pas de commandes manuelles, juste un push !** 🚀✨

**Prêt à coder en toute sécurité !** 🚀✨