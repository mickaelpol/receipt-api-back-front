# ⚡ Quick Start - Receipt API

## 🚀 Déployer en 3 étapes

### 1️⃣ **Configurer les secrets (une seule fois)**

```bash
make setup-gcp-secrets
```

Puis configurer `GCP_SA_KEY` dans GitHub :
- Aller sur : `https://github.com/[votre-repo]/settings/secrets/actions`
- Créer : `GCP_SA_KEY` = contenu de `backend/keys/sa-key.json`

### 2️⃣ **Push sur staging**

```bash
git add .
git commit -m "feat: mes changements"
git push origin staging
```

**→ Déploiement automatique sur staging !** ✨

### 3️⃣ **Push sur main (production)**

```bash
git checkout main
git merge staging
git push origin main
```

**→ Aller sur GitHub Actions et approuver le déploiement** ✅

---

## 📋 Commandes essentielles

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

---

## 🔗 Liens utiles

- **GitHub Actions** : `https://github.com/[votre-repo]/actions`
- **Cloud Build** : Console GCP → Cloud Build
- **Cloud Run** : Console GCP → Cloud Run
- **Secrets** : Console GCP → Secret Manager

---

## 📚 Documentation complète

- `DEPLOYMENT_GUIDE.md` - Guide de déploiement détaillé
- `SECURITY.md` - Sécurité et gestion des secrets
- `README.md` - Documentation complète du projet

---

**C'est tout ! Push = Déploiement automatique** 🎉
