# ✅ Checklist de Mise en Production

## 📋 Configuration Initiale (À faire UNE SEULE FOIS)

### 🔐 Secrets et Sécurité

- [ ] **Configurer les secrets dans GCP Secret Manager**
  ```bash
  make setup-gcp-secrets
  ```
  - [ ] `oauth-client-id` : Client ID OAuth Google
  - [ ] `spreadsheet-id` : ID du Google Sheet
  - [ ] `gcp-project-id` : ID du projet GCP
  - [ ] `gcp-processor-id` : ID du processeur Document AI
  - [ ] `allowed-emails` : Liste des emails autorisés
  - [ ] `who-columns` : Configuration JSON des colonnes

- [ ] **Configurer le secret GitHub**
  - Aller sur : `https://github.com/[repo]/settings/secrets/actions`
  - Créer : `GCP_SA_KEY` = contenu de `backend/keys/sa-key.json`

- [ ] **Vérifier les permissions IAM**
  ```bash
  gcloud projects get-iam-policy scan-document-ai
  ```
  - [ ] Service Account a `roles/secretmanager.secretAccessor`
  - [ ] Service Account a `roles/documentai.apiUser`
  - [ ] Service Account a `roles/run.admin`

### 📁 Fichiers et Configuration

- [ ] **Vérifier que `.gitignore` est configuré**
  - [ ] `backend/keys/sa-key.json` ignoré
  - [ ] `infra/.env` ignoré
  - [ ] Pas de secrets dans le code

- [ ] **Vérifier la structure du projet**
  - [ ] `infra/Dockerfile` existe
  - [ ] `backend/composer.json` existe
  - [ ] `frontend/index.html` existe
  - [ ] `.htaccess` existe à la racine

### 🧪 Tests Locaux

- [ ] **Application fonctionne localement**
  ```bash
  make up
  make smoke-test
  ```
  - [ ] http://localhost:8080/ accessible
  - [ ] http://localhost:8080/api/config fonctionne
  - [ ] Authentification Google fonctionne
  - [ ] Scan de tickets fonctionne

---

## 🚀 Déploiement Staging

### 📝 Avant le déploiement

- [ ] **Code prêt**
  - [ ] Tous les changements committés
  - [ ] Tests locaux passent
  - [ ] Cache-busting vérifié

- [ ] **Commit et push**
  ```bash
  git add .
  git commit -m "feat: description des changements"
  git push origin staging
  ```

### ✅ Vérifications post-déploiement

- [ ] **GitHub Actions**
  - [ ] Workflow "Deploy to Staging" terminé avec succès
  - [ ] Cache-busting appliqué
  - [ ] Build Docker réussi
  - [ ] Smoke tests passent

- [ ] **Cloud Build**
  - [ ] Build terminé avec succès
  - [ ] Toutes les étapes vertes
  - [ ] Pas d'erreurs dans les logs

- [ ] **Cloud Run Staging**
  ```bash
  make smoke-test-staging
  ```
  - [ ] Service déployé
  - [ ] URL accessible
  - [ ] `/health` retourne 200
  - [ ] `/ready` retourne 200
  - [ ] `/api/config` retourne 200

### 🧪 Tests fonctionnels Staging

- [ ] **Interface utilisateur**
  - [ ] Page se charge correctement
  - [ ] Assets (CSS/JS) chargés
  - [ ] Monitoring des services fonctionne (points verts)

- [ ] **Authentification**
  - [ ] Connexion Google fonctionne
  - [ ] Email autorisé accepté
  - [ ] Email non autorisé rejeté
  - [ ] Changement de compte fonctionne

- [ ] **Fonctionnalités**
  - [ ] Scan simple fonctionne
  - [ ] Scan multiple fonctionne
  - [ ] Enregistrement dans Google Sheet fonctionne
  - [ ] Sélection de la feuille fonctionne

---

## 🎯 Déploiement Production

### 📝 Pré-requis

- [ ] **Staging validé**
  - [ ] Tous les tests staging passent
  - [ ] Application stable depuis au moins 24h
  - [ ] Aucune erreur critique dans les logs

- [ ] **Préparation production**
  - [ ] Variables d'environnement production vérifiées
  - [ ] Emails autorisés mis à jour
  - [ ] WHO_COLUMNS configuré pour production

### 🚀 Merge et déploiement

- [ ] **Merge vers main**
  ```bash
  git checkout main
  git merge staging
  git push origin main
  ```

- [ ] **Approuver le déploiement**
  1. Aller sur GitHub Actions
  2. Cliquer sur "Deploy to Production"
  3. Cliquer sur "Review deployments"
  4. Cocher "production"
  5. Cliquer sur "Approve and deploy"

### ✅ Vérifications post-déploiement

- [ ] **GitHub Actions**
  - [ ] Workflow "Deploy to Production" terminé avec succès
  - [ ] Cache-busting appliqué
  - [ ] Build Docker réussi
  - [ ] Smoke tests passent

- [ ] **Cloud Build**
  - [ ] Build terminé avec succès
  - [ ] Toutes les étapes vertes
  - [ ] Pas d'erreurs dans les logs

- [ ] **Cloud Run Production**
  ```bash
  make smoke-test-prod
  ```
  - [ ] Service déployé
  - [ ] URL accessible
  - [ ] `/health` retourne 200
  - [ ] `/ready` retourne 200
  - [ ] `/api/config` retourne 200

### 🧪 Tests fonctionnels Production

- [ ] **Tests de base**
  - [ ] Page se charge en < 2 secondes
  - [ ] Pas d'erreurs dans la console
  - [ ] Assets chargés correctement
  - [ ] HTTPS activé

- [ ] **Authentification production**
  - [ ] Connexion avec email autorisé
  - [ ] Rejet d'email non autorisé
  - [ ] Déconnexion fonctionne

- [ ] **Fonctionnalités critiques**
  - [ ] Scan d'un ticket réel
  - [ ] Enregistrement dans le bon Google Sheet
  - [ ] Données correctement formatées

### 📊 Monitoring post-déploiement

- [ ] **Vérifier les logs (15 minutes après déploiement)**
  ```bash
  gcloud logging read "resource.type=cloud_run_revision" \
    --project=scan-document-ai \
    --limit=50
  ```
  - [ ] Pas d'erreurs 500
  - [ ] Pas d'erreurs d'authentification
  - [ ] Pas d'erreurs de secrets

- [ ] **Métriques Cloud Run**
  - [ ] Latence acceptable (< 2s)
  - [ ] CPU/Mémoire dans les limites
  - [ ] Aucune erreur 4xx/5xx

---

## 🚨 Rollback (en cas de problème)

### Si le déploiement échoue :

1. **Identifier le problème**
   ```bash
   # Voir les logs du dernier déploiement
   gcloud logging read "resource.type=cloud_run_revision" --limit=100
   ```

2. **Rollback immédiat**
   ```bash
   # Via la console Cloud Run
   # → Sélectionner une révision précédente
   # → Cliquer sur "Manage Traffic"
   # → Rediriger 100% du trafic vers l'ancienne révision
   ```

3. **Ou rollback via CLI**
   ```bash
   # Lister les révisions
   gcloud run revisions list --service=receipt-parser --region=europe-west9
   
   # Rediriger vers une révision précédente
   gcloud run services update-traffic receipt-parser \
     --to-revisions=[REVISION]=100 \
     --region=europe-west9
   ```

4. **Corriger et redéployer**
   ```bash
   # Corriger le problème
   git add .
   git commit -m "fix: correction du problème"
   git push origin staging  # Tester d'abord
   # Puis main après validation
   ```

---

## 📞 Support et Documentation

- **DEPLOYMENT_GUIDE.md** - Guide de déploiement détaillé
- **SECURITY.md** - Sécurité et secrets
- **README.md** - Documentation complète
- **QUICK_START.md** - Démarrage rapide

### Contacts
- Email : polmickael3@gmail.com
- GitHub Actions : `https://github.com/[repo]/actions`
- Cloud Console : `https://console.cloud.google.com`

---

## ✅ Récapitulatif

### Configuration initiale (une fois)
```bash
make setup-gcp-secrets        # Secrets dans GCP
# + Configurer GCP_SA_KEY dans GitHub
```

### Déploiement staging
```bash
git push origin staging       # → Automatique
make smoke-test-staging      # → Vérifier
```

### Déploiement production
```bash
git push origin main         # → Automatique
# → Approuver sur GitHub Actions
make smoke-test-prod        # → Vérifier
```

**C'est tout ! Le reste est automatique** 🎉
