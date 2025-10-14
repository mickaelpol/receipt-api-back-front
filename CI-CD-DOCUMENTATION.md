# 🚀 CI/CD Documentation - Scan2Sheet

## 📋 Vue d'ensemble

Ce projet utilise **Google Cloud Build** pour l'intégration continue et le déploiement continu (CI/CD).

**Pourquoi Google Cloud Build et pas GitHub Actions ?**
- ✅ **GRATUIT** : 120 minutes de build par jour (largement suffisant)
- ✅ **Intégré** : Déjà configuré pour le déploiement sur Cloud Run
- ✅ **Pas de problème de facturation** GitHub
- ✅ **Tests complets** : PHP, JavaScript, Docker, tout est testé
- ✅ **Rapide** : Builds en parallèle sur machines puissantes

---

## 🎯 Ce qui est testé automatiquement

À **chaque push** sur les branches `main`, `staging`, ou `dev`, Cloud Build exécute :

### Phase 1 : Tests & Quality Checks (3-5 minutes)

1. **PHP Syntax Check**
   - Vérifie que tous les fichiers PHP sont syntaxiquement corrects
   - Échoue si une seule erreur est trouvée

2. **PHPCS Linting**
   - Vérifie le respect des standards de code
   - Standards : PSR-12, phpcs.xml personnalisé

3. **PHPUnit Tests**
   - Exécute tous les tests unitaires
   - Suite : `Unit`

4. **Code Coverage** (optionnel, non-bloquant)
   - Génère un rapport de couverture de code
   - Utilise PCOV pour des performances optimales

5. **JavaScript Syntax Check**
   - Vérifie la syntaxe de tous les fichiers JS
   - Utilise Node.js 18

6. **Docker Validation**
   - Valide le Dockerfile
   - Vérifie la structure

### Phase 2 : Build & Deploy (5-8 minutes)

7. **Build Docker Image**
   - Construit l'image Docker
   - Tag avec le commit SHA et `latest`

8. **Push to Artifact Registry**
   - Pousse l'image vers Google Artifact Registry

9. **Deploy to Cloud Run**
   - Déploie l'application sur Cloud Run
   - Configuration : 1-10 instances, 512Mi RAM, 1 CPU

### Phase 3 : Smoke Tests (1-2 minutes)

10. **Smoke Tests Post-Deployment**
    - Teste les endpoints critiques :
      - `/` (Home page)
      - `/api/config`
      - `/api/ready`
      - `/api/health`
    - Retries 3 fois en cas d'échec

---

## 🔄 Workflow complet

```
git push
   ↓
Cloud Build détecte le push
   ↓
Phase 1: Tests (3-5 min)
   ├─ PHP Syntax ✓
   ├─ PHPCS ✓
   ├─ PHPUnit ✓
   ├─ Code Coverage ✓
   ├─ JS Syntax ✓
   └─ Docker Validation ✓
   ↓
Tests passed? ──NO─→ ❌ Build échoue, email envoyé
   ↓ YES
Phase 2: Build & Deploy (5-8 min)
   ├─ Build Docker image ✓
   ├─ Push to registry ✓
   └─ Deploy to Cloud Run ✓
   ↓
Phase 3: Smoke Tests (1-2 min)
   ├─ Test /api/config ✓
   ├─ Test /api/ready ✓
   └─ Test /api/health ✓
   ↓
✅ Déploiement réussi ! 🎉
```

**Temps total** : ~10-15 minutes

---

## 📊 Voir les logs des builds

### Console Web

```
https://console.cloud.google.com/cloud-build/builds?project=<votre-project-id>
```

Vous verrez :
- ✅ Builds réussis en vert
- ❌ Builds échoués en rouge
- ⏳ Builds en cours en bleu

### CLI (gcloud)

```bash
# Voir les builds récents
gcloud builds list --limit=10

# Voir les logs d'un build spécifique
gcloud builds log <BUILD_ID>

# Suivre un build en cours en temps réel
gcloud builds log <BUILD_ID> --stream
```

---

## 🐛 Que faire si un build échoue ?

### Étape 1 : Identifier l'étape qui a échoué

Regardez les logs dans la console Cloud Build. Chaque étape a un nom clair :

- `php-syntax-check` → Erreur de syntaxe PHP
- `phpcs-lint` → Violation des standards de code
- `phpunit-tests` → Test unitaire échoué
- `js-syntax-check` → Erreur de syntaxe JavaScript
- `build-image` → Problème Docker
- `smoke-tests` → L'application ne répond pas correctement

### Étape 2 : Reproduire l'erreur en local

**Pour PHP :**
```bash
# Syntax check
find backend -name "*.php" -not -path "*/vendor/*" -exec php -l {} \;

# PHPCS
cd backend
./vendor/bin/phpcs --standard=../phpcs.xml . --extensions=php

# PHPUnit
cd backend
php vendor/bin/phpunit --testsuite Unit
```

**Pour JavaScript :**
```bash
# Syntax check
find frontend/assets/js -name "*.js" -exec node --check {} \;
```

**Pour Docker :**
```bash
docker build -f infra/Dockerfile .
```

### Étape 3 : Corriger et re-push

```bash
# Corriger le problème
git add .
git commit -m "fix: corriger le problème X"
git push
```

Cloud Build va automatiquement relancer les tests.

---

## ⚙️ Configuration

### Fichiers importants

```
.
├── cloudbuild.yaml           # Configuration CI/CD principale
├── phpcs.xml                 # Standards de code PHP
├── backend/phpunit.xml       # Configuration PHPUnit
├── infra/Dockerfile          # Image Docker
└── .github/workflows/        # GitHub Actions (DÉSACTIVÉ)
    └── tests.yml.disabled
```

### Variables d'environnement

Configurées dans Cloud Build (substitutions) :

| Variable | Description | Exemple |
|----------|-------------|---------|
| `$PROJECT_ID` | ID du projet GCP | `264113083582` |
| `$_REGION` | Région de déploiement | `europe-west9` |
| `$_SERVICE_NAME` | Nom du service Cloud Run | `receipt-parser` |
| `$_ENV` | Environnement | `staging` ou `prod` |

### Secrets

Stockés dans Google Secret Manager :

- `oauth-client-id` → Google OAuth Client ID
- `spreadsheet-id` → ID de la Google Sheet
- `gcp-project-id` → ID du projet GCP
- `gcp-processor-id` → ID du processeur Document AI
- `allowed-emails` → Emails autorisés (JSON array)
- `who-columns` → Colonnes "Qui scanne" (JSON)

---

## 💰 Coûts

### Google Cloud Build - Free Tier

✅ **120 minutes de build par jour GRATUITES**

Votre usage actuel :
- ~10-15 minutes par build
- ~5-10 builds par jour (estimé)
- **Total : 50-150 minutes/jour**

👉 **Vous restez dans le free tier !**

### Au-delà du free tier

Si vous dépassez 120 minutes/jour :
- Prix : **$0.003 par build-minute**
- Exemple : 200 minutes/jour = 80 minutes payantes = **$0.24/jour** (~$7/mois)

**Mais vous ne devriez jamais dépasser 120 min/jour avec ce projet.**

---

## 🔧 Optimisation des builds

### Activer le cache (optionnel)

Pour accélérer `composer install`, ajoutez dans `cloudbuild.yaml` :

```yaml
options:
  volumes:
    - name: 'composer-cache'
      path: '/root/.composer'
```

### Paralléliser davantage

Les tests sont déjà parallélisés autant que possible, mais on peut aller plus loin :

```yaml
# Dans cloudbuild.yaml, modifier waitFor pour paralléliser
- name: 'php:8.1-cli'
  id: 'phpunit-tests'
  waitFor: ['install-composer-deps']  # Pas besoin d'attendre phpcs-lint

- name: 'node:18-slim'
  id: 'js-syntax-check'
  waitFor: ['-']  # Ne dépend de rien, lance immédiatement
```

---

## 🚨 Désactiver les tests (DÉCONSEILLÉ)

Si vous voulez déployer sans tests (urgence, debug) :

### Option 1 : Skip certaines étapes

Modifier `cloudbuild.yaml` et commenter les étapes :

```yaml
# - name: 'php:8.1-cli'
#   id: 'phpunit-tests'
#   ...
```

### Option 2 : Build manuel sans tests

```bash
# Build local
docker build -t test-image -f infra/Dockerfile .

# Push manuel vers Cloud Run
gcloud run deploy receipt-parser \
  --source . \
  --region europe-west9 \
  --allow-unauthenticated
```

⚠️ **Attention** : Vous sautez les tests de sécurité et qualité !

---

## 📈 Monitoring

### Voir le statut des builds

**Badge de status** (optionnel) :

Ajoutez dans votre `README.md` :

```markdown
![Cloud Build](https://storage.googleapis.com/cloud-build-badges/builds/<PROJECT_ID>/branches/main.svg)
```

### Notifications

Cloud Build envoie automatiquement des emails sur :
- ✅ Build réussi
- ❌ Build échoué

Configurer dans :
```
https://console.cloud.google.com/cloud-build/settings/notifications
```

---

## 🆚 Comparaison : GitHub Actions vs Cloud Build

| Feature | GitHub Actions | Google Cloud Build |
|---------|---------------|-------------------|
| **Prix** | 2000 min/mois gratuit | 120 min/jour (3600 min/mois) |
| **Problème facturation** | ❌ Compte bloqué | ✅ Pas de problème |
| **Intégration GCP** | Complexe | ✅ Native |
| **Vitesse machines** | Standard | ✅ E2_HIGHCPU_8 |
| **Cache Docker** | À configurer | ✅ Automatique |
| **Logs** | GitHub UI | ✅ Stackdriver |
| **Pour ce projet** | ❌ Désactivé | ✅ **RECOMMANDÉ** |

---

## 🎓 Commandes utiles

```bash
# Voir tous les builds
gcloud builds list

# Voir les détails d'un build
gcloud builds describe <BUILD_ID>

# Annuler un build en cours
gcloud builds cancel <BUILD_ID>

# Lancer un build manuellement
gcloud builds submit --config cloudbuild.yaml .

# Voir les logs en temps réel
gcloud builds log <BUILD_ID> --stream

# Voir les triggers configurés
gcloud builds triggers list
```

---

## ❓ FAQ

### Q : Pourquoi GitHub Actions est désactivé ?

**R :** Votre compte GitHub a un problème de facturation qui bloque les workflows. Google Cloud Build est gratuit (120 min/jour) et déjà configuré.

### Q : Les tests locaux (pre-commit hooks) sont-ils toujours actifs ?

**R :** Oui ! Les hooks Git locaux (`./githooks/pre-commit`) fonctionnent toujours. Cloud Build est une **double protection** après le push.

### Q : Puis-je réactiver GitHub Actions plus tard ?

**R :** Oui. Renommez `.github/workflows/tests.yml.disabled` en `.github/workflows/tests.yml`. Mais Cloud Build reste recommandé.

### Q : Est-ce que Cloud Build teste AVANT ou APRÈS le déploiement ?

**R :** **AVANT** ! Les tests (Phase 1) sont exécutés avant le build Docker. Si un test échoue, le déploiement est annulé.

### Q : Comment tester sans déployer ?

**R :** Créez une branche `test` et configurez un trigger Cloud Build sans déploiement :

```yaml
# Dans cloudbuild.yaml, ne garder que les steps de tests
steps:
  - name: 'php:8.1-cli'
    id: 'phpunit-tests'
    # ... seulement les tests
```

### Q : Les PRs (Pull Requests) déclenchent-elles des builds ?

**R :** Oui, si vous configurez un trigger sur les PRs dans Cloud Build :
```
https://console.cloud.google.com/cloud-build/triggers
```

---

## ✅ Checklist de migration

- [x] Cloud Build configuré avec tous les tests
- [x] PHPCS, PHPUnit, JS syntax check
- [x] Smoke tests post-déploiement
- [x] GitHub Actions désactivé (`.github/workflows/tests.yml.disabled`)
- [x] Documentation complète
- [ ] Configurer les notifications Cloud Build (optionnel)
- [ ] Ajouter un badge de build dans README.md (optionnel)

---

## 🎉 Conclusion

Vous avez maintenant une **CI/CD moderne, gratuite et robuste** avec Google Cloud Build !

**Avantages** :
- ✅ Pas de problème de facturation
- ✅ 120 minutes/jour gratuites (largement suffisant)
- ✅ Tests complets (PHP, JS, Docker)
- ✅ Déploiement automatique
- ✅ Smoke tests après déploiement
- ✅ Logs détaillés dans Stackdriver

**Prochaines étapes** :
1. Faire un `git push` et voir le magic happen ✨
2. Ouvrir https://console.cloud.google.com/cloud-build/builds
3. Regarder les tests passer en vert 🟢
4. Profiter du déploiement automatique ! 🚀

---

**Questions ou problèmes ?** Consultez les logs dans Cloud Build Console.
