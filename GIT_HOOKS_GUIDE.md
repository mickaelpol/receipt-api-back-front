# 🪝 Guide des Git Hooks

## 📋 Qu'est-ce qu'un Git Hook ?

Les Git hooks sont des scripts qui s'exécutent automatiquement à certains moments du workflow Git (commit, push, etc.). Ils permettent de :

- ✅ Vérifier la qualité du code avant commit
- ✅ Empêcher le commit de secrets
- ✅ Demander confirmation avant push vers production
- ✅ Bloquer les déploiements accidentels

## 🔧 Installation

```bash
make install-hooks
```

Ou manuellement :
```bash
./scripts/install-git-hooks.sh
```

## 📝 Pre-commit Hook

Exécuté **avant chaque commit**, vérifie :

### ✅ Vérifications PHP
- Syntaxe PHP valide (`php -l`)
- PHPCS (si configuré) pour le respect des standards
- Pas de fichiers `backend/keys/*.json` commités

### ✅ Vérifications JavaScript
- Syntaxe JavaScript valide
- Avertissement sur les `console.log`

### ✅ Vérifications de sécurité
- Aucune clé API (pattern `sk-...`, `AIza...`)
- Aucun fichier `.env` commité
- Aucun mot de passe en clair
- Pas de fichiers de clés dans `backend/keys/`

### ✅ Vérifications de structure
- Pas de fichiers > 1MB (sauf images)
- Structure de fichiers correcte

## 🚀 Pre-push Hook

Exécuté **avant chaque push**, vérifie :

### ✅ Tests PHPCS
```bash
./backend/vendor/bin/phpcs --standard=phpcs.xml backend/
```

### ✅ Dépendances
- `composer.lock` à jour si `composer.json` modifié

### ✅ Configuration Docker
- `Dockerfile` valide
- `.htaccess` copié dans l'image

### ✅ Cloud Build
- `cloudbuild.yaml` valide (YAML)

### ⚠️ Confirmation pour push vers `main`
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

## 🔓 Bypasser les hooks (déconseillé)

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

## 🧪 Tester les hooks

### Tester pre-commit

```bash
# Créer un fichier avec une erreur de syntaxe
echo "<?php echo 'test'" > backend/test.php

# Tenter de commit
git add backend/test.php
git commit -m "test"

# Le hook devrait bloquer le commit
```

### Tester pre-push

```bash
# Modifier un fichier
echo "// test" >> backend/index.php
git add backend/index.php
git commit -m "test"

# Tenter de push vers main
git push origin main

# Le hook devrait demander confirmation
```

## 📊 Exemples de sorties

### ✅ Commit réussi

```
🔍 Pre-commit checks...

📝 Vérification PHP...
Fichiers PHP modifiés:
  - backend/app.php
  → Linting PHP avec PHPCS...
✅ PHP OK

📝 Vérification JavaScript...
  Aucun fichier JavaScript modifié

🔐 Vérification des secrets...
✅ Aucun secret détecté

📁 Vérification de la structure...
✅ Structure OK

╔════════════════════════════════════════╗
║  ✅ ALL PRE-COMMIT CHECKS PASSED      ║
╚════════════════════════════════════════╝
```

### ❌ Commit bloqué (secret détecté)

```
🔍 Pre-commit checks...

📝 Vérification PHP...
✅ PHP OK

🔐 Vérification des secrets...
❌ ERREUR: Tentative de commit d'un fichier de clés !
Fichiers bloqués:
  - backend/keys/sa-key.json

╔════════════════════════════════════════╗
║  ❌ PRE-COMMIT CHECKS FAILED          ║
║  Corrigez les erreurs ci-dessus       ║
╚════════════════════════════════════════╝

Pour bypasser (déconseillé): git commit --no-verify
```

### ⚠️ Push vers main

```
🚀 Pre-push checks...

📌 Push vers la branche: main

🧪 Tests PHPCS...
✅ PHPCS OK

📦 Vérification des dépendances...
✅ Dépendances OK

🐳 Vérification Docker...
✅ Docker OK

☁️  Vérification Cloud Build...
✅ cloudbuild.yaml valide

╔════════════════════════════════════════════════════════╗
║  ⚠️  ATTENTION: Push vers MAIN                        ║
║                                                        ║
║  Cela va déclencher le déploiement sur Cloud Run !   ║
╚════════════════════════════════════════════════════════╝

Voulez-vous vraiment déployer en production ? (y/N) y

🧪 Tests de smoke recommandés...
Voulez-vous lancer 'make smoke-test' maintenant ? (y/N) y

🧪 Tests de smoke locaux...
Testing http://localhost:8080...
✅ Local smoke tests passed

╔════════════════════════════════════════╗
║  ✅ ALL PRE-PUSH CHECKS PASSED        ║
║  🚀 Push autorisé                     ║
╚════════════════════════════════════════╝
```

## 🔄 Désinstaller les hooks

```bash
rm .git/hooks/pre-commit
rm .git/hooks/pre-push
```

## 📁 Fichiers concernés

```
.githooks/
├── pre-commit      # Hook pre-commit
└── pre-push        # Hook pre-push

scripts/
└── install-git-hooks.sh  # Script d'installation

.git/hooks/         # Hooks actifs (générés)
├── pre-commit
└── pre-push
```

## 🎯 Recommandations

1. ✅ **Toujours avoir les hooks installés** en développement
2. ✅ **Ne pas bypasser** sauf urgence absolue
3. ✅ **Lancer `make smoke-test`** avant push vers main
4. ✅ **Réinstaller après `git clone`** :
   ```bash
   make install-hooks
   ```

## 🆘 Dépannage

### Les hooks ne s'exécutent pas

```bash
# Vérifier que les hooks sont exécutables
ls -la .git/hooks/

# Réinstaller
make install-hooks
```

### "Permission denied"

```bash
chmod +x .git/hooks/pre-commit
chmod +x .git/hooks/pre-push
```

### Hooks trop stricts

Vous pouvez modifier les hooks dans `.githooks/` puis réinstaller :
```bash
# Modifier
nano .githooks/pre-commit

# Réinstaller
make install-hooks
```

## 🎉 Avantages

- ✅ **Sécurité** : Empêche le commit de secrets
- ✅ **Qualité** : Code vérifié avant commit
- ✅ **Confiance** : Confirmation avant déploiement
- ✅ **Rapidité** : Détection des erreurs avant CI/CD
- ✅ **Économie** : Moins de builds GitHub Actions

