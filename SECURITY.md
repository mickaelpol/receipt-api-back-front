# 🔐 Sécurité - Receipt API

## ✅ Bonnes pratiques de sécurité implémentées

### **1. Gestion des secrets**

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

### **2. Architecture de sécurité**

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

### **3. Niveaux de sécurité**

| Niveau | Méthode | Sécurité | Recommandation |
|--------|---------|----------|----------------|
| 🔴 **Danger** | Secrets en dur dans le code | ❌ Très faible | JAMAIS |
| 🟡 **Moyen** | GitHub Secrets | ⚠️ Moyenne | Acceptable pour CI/CD uniquement |
| 🟢 **Excellent** | **Google Secret Manager** | ✅ Très élevée | **RECOMMANDÉ** |

## 🔧 Configuration des secrets (Méthode sécurisée)

### **Étape 1 : Configuration initiale dans GCP**

```bash
# Configurer tous les secrets dans Google Secret Manager
make setup-gcp-secrets
```

Ce script va :
1. Activer l'API Secret Manager
2. Créer ou mettre à jour chaque secret
3. Configurer les permissions IAM
4. **Aucun secret ne sera stocké dans GitHub ou dans le code**

### **Étape 2 : Vérification des secrets**

```bash
# Lister les secrets dans GCP
gcloud secrets list --project=scan-document-ai

# Voir les versions d'un secret (sans voir la valeur)
gcloud secrets versions list oauth-client-id --project=scan-document-ai
```

### **Étape 3 : Accès aux secrets**

Les secrets sont automatiquement injectés dans Cloud Run via :
```yaml
# cloudbuild.yaml (ligne 101-102)
--set-secrets
GOOGLE_OAUTH_CLIENT_ID=oauth-client-id:latest,
SPREADSHEET_ID=spreadsheet-id:latest,
...
```

## 🔒 Sécurité par couche

### **1. Authentification**
- ✅ Google OAuth 2.0
- ✅ Validation des tokens
- ✅ Vérification de l'audience
- ✅ Liste d'emails autorisés

### **2. Autorisation**
- ✅ Service Account avec permissions minimales
- ✅ IAM roles strictes
- ✅ Protection des endpoints API
- ✅ CORS configuré

### **3. Secrets**
- ✅ Google Secret Manager (chiffrement au repos)
- ✅ Transmission sécurisée (TLS)
- ✅ Pas de secrets dans le code
- ✅ Pas de secrets dans les logs
- ✅ Rotation possible

### **4. Infrastructure**
- ✅ Cloud Run (isolation des conteneurs)
- ✅ VPC si nécessaire
- ✅ HTTPS obligatoire
- ✅ Firewall configuré

## 🛡️ Permissions IAM recommandées

### Service Account `docai-sa@scan-document-ai.iam.gserviceaccount.com`

**Permissions minimales requises :**
```yaml
roles/secretmanager.secretAccessor  # Accès aux secrets
roles/documentai.apiUser             # Document AI
roles/sheets.editor                   # Google Sheets
```

**Configuration :**
```bash
# Configurer les permissions
PROJECT_ID="scan-document-ai"
SA="docai-sa@${PROJECT_ID}.iam.gserviceaccount.com"

# Accès aux secrets (automatique via setup-gcp-secrets.sh)
gcloud secrets add-iam-policy-binding oauth-client-id \
  --member="serviceAccount:$SA" \
  --role="roles/secretmanager.secretAccessor" \
  --project=$PROJECT_ID
```

## 📋 Checklist de sécurité

### **Avant de déployer en production :**
- [ ] Tous les secrets dans Google Secret Manager
- [ ] Aucun fichier `.env` ou `sa-key.json` dans Git
- [ ] `.gitignore` à jour
- [ ] Permissions IAM configurées
- [ ] CORS correctement configuré
- [ ] Liste d'emails autorisés à jour
- [ ] HTTPS activé sur Cloud Run
- [ ] Logs sécurisés (pas de secrets exposés)

### **Fichiers à NE JAMAIS commiter :**
```
# .gitignore
backend/keys/sa-key.json    # Service Account
infra/.env                  # Variables d'environnement
*.pem
*.key
*.p12
*credentials*.json
```

## 🔍 Audit de sécurité

### **Vérifications régulières :**

```bash
# 1. Vérifier qu'aucun secret n'est dans Git
git log --all --full-history --source --find-object | grep -i "password\|secret\|key"

# 2. Vérifier les permissions IAM
gcloud projects get-iam-policy scan-document-ai

# 3. Vérifier les secrets dans Secret Manager
gcloud secrets list --project=scan-document-ai

# 4. Auditer les accès aux secrets
gcloud logging read "resource.type=secret_manager" --project=scan-document-ai --limit=50
```

## 🚨 En cas de compromission

### **Si un secret est exposé :**

1. **Révoquer immédiatement** :
   ```bash
   # Désactiver la version compromise
   gcloud secrets versions disable VERSION --secret=SECRET_NAME --project=scan-document-ai
   ```

2. **Créer une nouvelle version** :
   ```bash
   # Créer un nouveau secret
   make setup-gcp-secrets
   ```

3. **Redéployer** :
   ```bash
   git push origin staging  # Test
   git push origin main     # Production
   ```

4. **Auditer les accès** :
   ```bash
   gcloud logging read "resource.type=cloud_run_revision" --project=scan-document-ai --limit=100
   ```

## 📚 Ressources

- [Google Secret Manager Best Practices](https://cloud.google.com/secret-manager/docs/best-practices)
- [Cloud Run Security](https://cloud.google.com/run/docs/securing/using-iam)
- [OAuth 2.0 Security](https://oauth.net/2/security/)

## ✅ Avantages de notre approche

1. **🔒 Secrets chiffrés** : Google Secret Manager chiffre automatiquement
2. **🔐 Accès contrôlé** : Uniquement via IAM (Service Account)
3. **📊 Auditable** : Tous les accès sont loggés
4. **♻️ Rotation facile** : Versions multiples supportées
5. **🚀 CI/CD simplifié** : Pas de secrets dans GitHub Actions
6. **💪 Résilient** : Haute disponibilité Google Cloud
7. **📝 Conforme** : Standards de sécurité Google Cloud

---

**En résumé : Aucun secret n'est stocké dans GitHub, ni dans le code. Tout est sécurisé dans Google Secret Manager.** ✅🔐
