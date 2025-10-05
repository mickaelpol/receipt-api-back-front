# 📄 Receipt API - Scanner de Tickets vers Google Sheets

Application web moderne pour scanner des tickets de caisse et les enregistrer automatiquement dans Google Sheets via Google Document AI.

## 🚀 Fonctionnalités

### ✨ Scanner de tickets
- **Mode simple** : Scanner un ticket à la fois avec aperçu
- **Mode multiple** : Scanner jusqu'à 10 tickets en une fois
- **Reconnaissance automatique** : Extraction automatique du montant, date et enseigne
- **Interface mobile** : Optimisée pour smartphones et tablettes

### 🔐 Authentification Google
- **Connexion sécurisée** : OAuth2 avec Google Identity
- **Session persistante** : Reconnexion automatique silencieuse
- **Gestion des tokens** : Stockage sécurisé en mémoire uniquement

### 📊 Intégration Google Sheets
- **Écriture automatique** : Enregistrement direct dans Google Sheets
- **Gestion multi-utilisateurs** : Colonnes dédiées par utilisateur
- **Formatage intelligent** : Dates formatées automatiquement

## 🏗️ Architecture

### Vue d'ensemble
L'application suit une architecture moderne avec séparation claire des responsabilités :

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   Infrastructure │
│   (SPA)         │    │   (API)         │    │   (Docker)       │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ • HTML5/CSS/JS  │◄──►│ • PHP 8.2       │    │ • Docker        │
│ • Bootstrap 5   │    │ • Apache        │    │ • Cloud Run     │
│ • Google OAuth  │    │ • Document AI   │    │ • CI/CD         │
│ • PWA Ready     │    │ • Sheets API    │    │ • Monitoring    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Composants principaux

#### Frontend (SPA)
- **HTML5** : Interface responsive avec Bootstrap 5
- **JavaScript ES6+** : Application côté client moderne
- **Google Identity** : Authentification OAuth2
- **PWA Ready** : Optimisé pour mobile

#### Backend (API)
- **PHP 8.2** : API REST moderne
- **Google Document AI** : Reconnaissance de texte et extraction de données
- **Google Sheets API** : Intégration avec les feuilles de calcul
- **Apache** : Serveur web avec routing intelligent

#### Infrastructure
- **Docker** : Containerisation pour développement et production
- **Cloud Run** : Déploiement serverless sur Google Cloud
- **Single Container** : Frontend et backend dans un seul container

## 🛠️ Installation et Configuration

### Prérequis
- Docker & Docker Compose
- Compte Google Cloud avec APIs activées
- Service Account avec permissions appropriées

### Configuration locale

1. **Cloner le projet**
```bash
git clone <repository-url>
cd receipt-api-local-google-parser
```

2. **Configurer les variables d'environnement**
```bash
cp .env.example .env
# Éditer .env avec vos valeurs
```

3. **Configurer les clés Google**
```bash
# Placer votre service-account.json dans backend/keys/
mkdir -p backend/keys
cp your-service-account.json backend/keys/service-account.json
chmod 600 backend/keys/service-account.json
```

4. **Démarrer l'application**
```bash
make up
```

5. **Accéder à l'application**
```
http://localhost:8080
```

### Variables d'environnement

| Variable | Description | Exemple |
|----------|-------------|---------|
| `GOOGLE_OAUTH_CLIENT_ID` | ID client OAuth Google | `123456789.apps.googleusercontent.com` |
| `SPREADSHEET_ID` | ID de la feuille Google Sheets | `1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms` |
| `DEFAULT_SHEET` | Nom de la feuille par défaut | `Feuille 1` |
| `GCP_PROJECT_ID` | ID du projet Google Cloud | `my-project-123` |
| `GCP_LOCATION` | Région Google Cloud | `eu` |
| `GCP_PROCESSOR_ID` | ID du processeur Document AI | `1234567890` |
| `ALLOWED_EMAILS` | Emails autorisés (séparés par virgules) | `user1@example.com,user2@example.com` |
| `ALLOWED_ORIGINS` | Origines CORS autorisées | `http://localhost:8080` |
| `WHO_COLUMNS` | Configuration des colonnes utilisateurs | `{"Sabrina":["K","L","M"],"Mickael":["O","P","Q"]}` |
| `MAX_BATCH_UPLOADS` | Nombre max de tickets par batch | `10` |
| `DEBUG` | Mode debug (0/1) | `0` |

### APIs Google requises
- Google Document AI API
- Google Sheets API
- Google Identity API

## 🚀 Déploiement

### Cloud Run (Production)

1. **Build et déploiement automatique**
```bash
# Via Cloud Build (recommandé)
gcloud builds submit --config cloudbuild.yaml

# Ou manuellement
gcloud run deploy receipt-parser \
  --source . \
  --platform managed \
  --region europe-west9 \
  --allow-unauthenticated
```

2. **Configuration des secrets**
```bash
# Migrer les variables vers Secret Manager
gcloud secrets create receipt-config --data-file=.env
```

### Variables d'environnement Cloud Run
- `GOOGLE_OAUTH_CLIENT_ID`
- `SPREADSHEET_ID`
- `GCP_PROJECT_ID`
- `GCP_PROCESSOR_ID`
- `ALLOWED_EMAILS`
- `WHO_COLUMNS`

## 🧪 Développement

### Commandes utiles

```bash
# Démarrer l'application
make up

# Voir les logs
make logs

# Redémarrer
make restart

# Shell dans le container
make sh-app

# Installer les dépendances
make install
```

### Qualité de code

```bash
# Vérifier la qualité (détection debug + données sensibles)
make check-quality

# Linter le code (ESLint + PHP CodeSniffer)
make lint

# Formater le code automatiquement
make format

# Tests de fumée (vérification complète)
./scripts/smoke-tests.sh

# Vérifier la séparation frontend/backend
./scripts/check-frontend-backend-separation.sh
```

**Outils inclus dans le container :**
- **ESLint** : Linting JavaScript avec configuration moderne
- **PHP CodeSniffer** : Linting PHP selon PSR-12
- **Script de vérification** : Détection des `console.log` et données sensibles

### Architecture du projet

L'application suit une architecture moderne avec séparation claire des responsabilités :

```
receipt-api-local-google-parser/
├── 📁 frontend/            # Interface utilisateur (SPA)
│   ├── index.html          # Application web principale
│   ├── assets/             # CSS, JS, images
│   └── .htaccess           # Routage SPA + API
├── 📁 backend/             # API PHP (API uniquement)
│   ├── index.php           # Routeur API principal
│   ├── app.php             # Déclarations de fonctions
│   ├── bootstrap.php       # Initialisation + autoload
│   ├── .htaccess           # Sécurité backend
│   └── keys/               # Clés de service Google
├── 📁 infra/               # Infrastructure
│   ├── docker-compose.yml  # Configuration Docker
│   ├── .env                # Variables d'environnement
│   └── docker/php/         # Configuration PHP
├── 📁 scripts/             # Scripts utilitaires
│   ├── smoke-tests.sh      # Tests de fumée
│   └── check-frontend-backend-separation.sh
└── 📁 tests/               # Tests unitaires
```

**Séparation des responsabilités :**
- **Frontend** : SPA servie à la racine `/` avec routage client-side
- **Backend** : API accessible uniquement via `/api/*`
- **Infrastructure** : Docker, CI/CD, configuration

## 🔒 Sécurité

### Headers de sécurité
- **CSP** : Content Security Policy stricte
- **HSTS** : HTTP Strict Transport Security
- **X-Frame-Options** : Protection contre le clickjacking
- **X-Content-Type-Options** : Protection MIME sniffing

### Authentification
- **OAuth2** : Authentification Google sécurisée
- **Tokens en mémoire** : Pas de stockage persistant des tokens
- **Session management** : Gestion automatique des sessions

### Données sensibles
- **Masquage automatique** : Tokens et emails masqués dans les logs
- **Validation stricte** : Vérification des emails autorisés
- **CORS configuré** : Politique CORS restrictive

## 📊 Monitoring et Logging

### Endpoints de santé
- `GET /api/health` : Statut de l'application
- `GET /api/ready` : Vérification de la disponibilité des services

### Logging structuré
- **Format JSON** : Logs structurés avec timestamps
- **Niveaux de log** : info, warn, error
- **Masquage des données** : Données sensibles automatiquement masquées
- **Politique de logs** : En production, seuls les erreurs et warnings sont loggés

### Exemple de log structuré
```json
{
  "timestamp": "2024-01-15T10:30:00Z",
  "level": "info",
  "message": "User authenticated",
  "context": {
    "user_id": "user123",
    "method": "oauth",
    "timestamp": 1705312200
  }
}
```

## 🐛 Dépannage

### Problèmes courants

1. **Erreur 401 - Connexion non autorisée**
   - Vérifier `GOOGLE_OAUTH_CLIENT_ID`
   - Vérifier que l'email est dans `ALLOWED_EMAILS`

2. **Erreur 500 - Service indisponible**
   - Vérifier les logs : `make logs`
   - Vérifier la configuration DocAI

3. **Assets non chargés**
   - Vérifier la configuration Apache
   - Vérifier les permissions des fichiers

### Logs et debugging

```bash
# Voir les logs en temps réel
make logs

# Logs détaillés (mode debug)
DEBUG=1 make restart
make logs
```

## 📋 Standards de Code

### PHP (PSR-12)
- **Indentation** : 4 espaces (pas de tabs)
- **Longueur de ligne** : Limite douce 120 caractères, limite dure 140 caractères
- **Nommage** : camelCase pour les variables, PascalCase pour les classes
- **Accolades** : Accolades ouvrantes sur la même ligne, fermantes sur une nouvelle ligne

### JavaScript (ESLint)
- **Indentation** : 4 espaces
- **Guillemets** : Guillemets simples
- **Point-virgules** : Toujours requis
- **Pas de console.log** : Le code de production ne doit pas contenir de statements de debug

### Gestion des erreurs
- **Production** : Messages d'erreur génériques
- **Développement** : Messages d'erreur détaillés (DEBUG=1)
- **Jamais exposer** : Chemins internes ou informations sensibles

### Pratiques interdites
- `console.log()`, `console.warn()`, `console.error()`
- `var_dump()`, `print_r()`, `var_export()`
- Opérateur `@` pour la suppression d'erreurs
- Échecs silencieux
- Valeurs de retour ignorées

## 🤝 Contribution

### Workflow
1. Fork du projet
2. Créer une branche feature
3. Développer avec les standards
4. Tester avec `make check-quality`
5. Créer une Pull Request

### Checklist de contribution
- [ ] Code linter sans erreurs
- [ ] Aucun statement de debug
- [ ] Documentation à jour
- [ ] Tests passent
- [ ] Sécurité vérifiée

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 🆘 Support

Pour toute question ou problème :
1. Consulter la documentation
2. Vérifier les issues existantes
3. Créer une nouvelle issue avec les détails

---

**Développé avec ❤️ pour simplifier la gestion des tickets de caisse**