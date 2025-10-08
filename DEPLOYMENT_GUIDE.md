# 🚀 Guide de Déploiement Automatique - Receipt API

## 🎯 Principe simple

**Push sur GitHub → Déploiement automatique sur Cloud Run**

- Push sur `staging` → Déploiement staging automatique
- Push sur `main` → Déploiement production automatique (avec approbation)

## 📋 Configuration initiale (À faire UNE SEULE FOIS)

### **Étape 1 : Configurer les secrets dans Google Secret Manager**

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

### **Étape 2 : Configurer le secret GitHub (Service Account)**

**Seul secret requis dans GitHub :**

1. Aller sur GitHub : `https://github.com/[votre-repo]/settings/secrets/actions`

2. Créer un nouveau secret :
   - **Nom** : `GCP_SA_KEY`
   - **Valeur** : Contenu complet du fichier `backend/keys/sa-key.json`

**Pourquoi ce secret dans GitHub ?**
- C'est le seul secret nécessaire dans GitHub
- Il permet à GitHub Actions de s'authentifier sur GCP
- Tous les autres secrets sont dans Google Secret Manager (plus sécurisé)

### **Étape 3 : Vérifier la configuration**

```bash
# Vérifier que l'app fonctionne localement
make up
make smoke-test

# Vérifier les secrets dans GCP
gcloud secrets list --project=scan-document-ai
```

---

## 🚀 Déploiement Automatique

### **Déploiement Staging (automatique)**

```bash
# 1. Faire vos modifications
# ... éditer le code ...

# 2. Commit
git add .
git commit -m "feat: vos changements"

# 3. Push sur staging → DÉPLOIEMENT AUTOMATIQUE
git push origin staging
```

**Ce qui se passe automatiquement :**
1. ✅ Cache-busting automatique
2. ✅ Build Docker avec `infra/Dockerfile`
3. ✅ Push vers Artifact Registry
4. ✅ Déploiement sur Cloud Run (staging)
5. ✅ Smoke tests automatiques
6. ✅ Notification de succès/échec

**Suivre le déploiement :**
- GitHub Actions : `https://github.com/[votre-repo]/actions`
- Cloud Build : Console GCP → Cloud Build
- Logs Cloud Run : Console GCP → Cloud Run → receipt-parser → Logs

### **Déploiement Production (automatique avec approbation)**

```bash
# 1. Vérifier que staging fonctionne
# Tester l'URL de staging

# 2. Merger staging vers main
git checkout main
git merge staging
git commit -m "release: déploiement production"

# 3. Push sur main → DÉPLOIEMENT AUTOMATIQUE (avec approbation)
git push origin main
```

**Ce qui se passe automatiquement :**
1. ✅ Cache-busting automatique
2. ✅ Build Docker avec `infra/Dockerfile`
3. ✅ Push vers Artifact Registry
4. ⏸️ **Attente d'approbation manuelle** (sécurité production)
5. ✅ Après approbation : Déploiement sur Cloud Run (production)
6. ✅ Smoke tests automatiques
7. ✅ Notification de succès/échec

**Approuver le déploiement production :**
1. Aller sur GitHub Actions : `https://github.com/[votre-repo]/actions`
2. Cliquer sur le workflow "Deploy to Production"
3. Cliquer sur **"Review deployments"**
4. Cocher **"production"**
5. Cliquer sur **"Approve and deploy"**

---

## 📊 Vérification du déploiement

### **1. Via GitHub Actions**
```
https://github.com/[votre-repo]/actions
```
- ✅ Tous les steps doivent être verts
- ✅ Smoke tests doivent passer

### **2. Via Cloud Build**
```bash
# Lister les builds récents
gcloud builds list --limit=5 --project=scan-document-ai

# Voir les logs d'un build
gcloud builds log [BUILD_ID] --project=scan-document-ai
```

### **3. Via Cloud Run**
```bash
# Obtenir l'URL du service
gcloud run services describe receipt-parser \
  --region=europe-west9 \
  --format='value(status.url)'

# Tester les endpoints
SERVICE_URL="[URL_DU_SERVICE]"
curl -f $SERVICE_URL/
curl -f $SERVICE_URL/api/config
curl -f $SERVICE_URL/health
curl -f $SERVICE_URL/ready
```

### **4. Tests automatiques**
```bash
# Tests locaux
make smoke-test

# Tests staging
make smoke-test-staging

# Tests production
make smoke-test-prod
```

---

## 🔧 Workflow complet résumé

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

---

## 🛠️ Résolution des problèmes

### **Problème : 403 Forbidden sur Cloud Run**
**Symptôme :** `GET https://[url]/ 403 (Forbidden)` et `/api/config 404`

**Cause :** Le fichier `.htaccess` n'est pas copié dans l'image Docker

**Solution :**
✅ **Déjà corrigé** : Le `.htaccess` est maintenant copié dans `infra/Dockerfile`

### **Problème : "Dockerfile not found"**
✅ **Déjà corrigé** : Le chemin est maintenant `infra/Dockerfile` dans `cloudbuild.yaml`

### **Problème : "bash: line 3: gcloud: command not found" dans smoke tests**
**Cause :** Utilisation de l'image `gcr.io/cloud-builders/curl` qui ne contient pas `gcloud`

**Solution :**
✅ **Déjà corrigé** : Utilisation de `gcr.io/cloud-builders/gcloud` pour les smoke tests

### **Problème : "Permission denied" dans Cloud Build**
```bash
# Vérifier les permissions du Service Account
gcloud projects get-iam-policy scan-document-ai \
  --flatten="bindings[].members" \
  --filter="bindings.members:docai-sa@scan-document-ai.iam.gserviceaccount.com"
```

**Permissions requises :**
- `roles/secretmanager.secretAccessor`
- `roles/documentai.apiUser`
- `roles/editor` (ou `roles/run.admin`)

### **Problème : "Secrets not found"**
```bash
# Vérifier que les secrets existent
gcloud secrets list --project=scan-document-ai

# Reconfigurer si nécessaire
make setup-gcp-secrets
```

### **Problème : "Build timeout"**
✅ **Déjà configuré** : Timeout de 20 minutes dans `cloudbuild.yaml`

Si le build est toujours trop long :
```yaml
# cloudbuild.yaml (ligne 191)
timeout: '1800s'  # 30 minutes
```

### **Problème : "Smoke tests failed"**
```bash
# Tester manuellement les endpoints
SERVICE_URL="[URL_DU_SERVICE]"
curl -v $SERVICE_URL/health
curl -v $SERVICE_URL/ready
curl -v $SERVICE_URL/api/config

# Vérifier les logs Cloud Run
gcloud logging read "resource.type=cloud_run_revision" \
  --project=scan-document-ai \
  --limit=50
```

---

## 📚 Commandes utiles

### **Développement**
```bash
make up                    # Démarrer localement
make down                  # Arrêter
make logs                  # Voir les logs
make smoke-test           # Tester localement
make cache-bust           # Cache-busting manuel
```

### **Déploiement**
```bash
# Push = Déploiement automatique
git push origin staging    # → Déploiement staging
git push origin main       # → Déploiement production (avec approbation)
```

### **Vérification**
```bash
make smoke-test-staging    # Tester staging
make smoke-test-prod       # Tester production
make test-cloudbuild       # Tester le build localement
```

### **Secrets**
```bash
make setup-gcp-secrets     # Configurer les secrets GCP (une seule fois)
gcloud secrets list --project=scan-document-ai  # Lister les secrets
```

### **Monitoring**
```bash
# URL du service
gcloud run services describe receipt-parser \
  --region=europe-west9 \
  --format='value(status.url)'

# Logs en temps réel
gcloud logging tail "resource.type=cloud_run_revision" \
  --project=scan-document-ai

# Métriques
gcloud run services describe receipt-parser \
  --region=europe-west9 \
  --format='value(status.traffic)'
```

---

## ✅ Checklist de déploiement

### **Configuration initiale (une seule fois)**
- [ ] Secrets configurés dans GCP Secret Manager (`make setup-gcp-secrets`)
- [ ] Secret `GCP_SA_KEY` configuré dans GitHub
- [ ] Service Account avec bonnes permissions
- [ ] Application testée localement (`make smoke-test`)

### **Avant chaque déploiement**
- [ ] Tests locaux passent (`make smoke-test`)
- [ ] Code committé et poussé sur `staging`
- [ ] Déploiement staging vérifié et testé

### **Déploiement production**
- [ ] Staging validé et fonctionnel
- [ ] Merge `staging` vers `main`
- [ ] Push sur `main`
- [ ] Approbation manuelle sur GitHub Actions
- [ ] Tests de smoke post-déploiement

---

## 🎉 C'est tout !

**Le processus est maintenant complètement automatisé :**

1. **Vous codez** → `git add` + `git commit`
2. **Vous pushez** → `git push origin staging` ou `git push origin main`
3. **Le reste est automatique** → Cloud Build déploie sur Cloud Run

**Pas de configuration complexe, pas de commandes manuelles, juste un push !** 🚀✨

---

## 📞 Support

- **Documentation sécurité** : Voir `SECURITY.md`
- **README complet** : Voir `README.md`
- **Logs Cloud Run** : Console GCP → Cloud Run → Logs
- **GitHub Actions** : `https://github.com/[votre-repo]/actions`