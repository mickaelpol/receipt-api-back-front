# 📱 Test de la notification de mise à jour - Guide simple

## 🎯 Objectif

Voir la notification "✨ Nouvelle version disponible !" sur votre mobile.

---

## ⚠️ IMPORTANT : Comprendre le test

La notification apparaît SEULEMENT quand :
```
Version sur mobile:  v6  (ancienne)
Version sur serveur: v7  (nouvelle)
                      ↓
              NOTIFICATION ! 🎉
```

**Si mobile = v6 ET serveur = v6 → PAS de notification** (normal, c'est la même version !)

---

## 📋 Étape 1 : Vérifier quelle version est sur mobile

### Sur votre téléphone :

1. **Ouvrir** l'application PWA Scan2Sheet
2. **Regarder** en haut à gauche, il y a maintenant un badge bleu qui dit **`⟳ v6`** (ou v? si ça charge)
3. **Noter** la version affichée

```
┌─────────────────────────────┐
│ ● App  ● Services  ⟳ v6    │  ← ICI !
│                             │
│      Scan2Sheet             │
└─────────────────────────────┘
```

---

## 📋 Étape 2a : SI mobile affiche "⟳ v6"

**Parfait !** Votre mobile a déjà la v6. Maintenant on va déployer la v7.

### Sur votre PC :

```bash
# 1. Ouvrir le fichier
nano frontend/service-worker.js

# 2. Ligne 10, changer :
const CACHE_VERSION = 'v6';
# en :
const CACHE_VERSION = 'v7';

# 3. Sauvegarder (Ctrl+O, Enter, Ctrl+X)

# 4. Commiter
git add frontend/service-worker.js
git commit -m "test(pwa): test notification v7"

# 5. Déployer
git push
```

### Sur votre téléphone (APRÈS 3 minutes) :

1. **Ouvrir** l'app PWA
2. **Attendre** 60-90 secondes
3. **Regarder** : Une notification devrait apparaître ! 🎉

```
┌──────────────────────────────────────┐
│ ✨ Nouvelle version disponible !    │
│ Cliquez sur "Actualiser"             │
│ [Actualiser] [✕]                     │
└──────────────────────────────────────┘
```

4. **Cliquer** sur "Actualiser"
5. **L'app recharge** et maintenant le badge dit **`⟳ v7`** !

---

## 📋 Étape 2b : SI mobile affiche "⟳ v5" ou moins

**Votre mobile a une ancienne version.** Déployons la v6 d'abord.

### Sur votre PC :

```bash
# Vérifier quelle version est dans le code
cat frontend/service-worker.js | grep CACHE_VERSION

# Si c'est v6 :
git push

# Attendre 3 minutes, puis sur mobile :
# - Fermer l'app complètement (swipe up)
# - Ré-ouvrir l'app
# - Attendre 60 secondes
# - Notification devrait apparaître !
```

---

## 📋 Étape 2c : SI mobile affiche "⟳ v?" ou rien

Le badge ne fonctionne pas encore. Pas grave, on peut quand même tester.

### Diagnostic mobile (méthode rapide) :

1. **Sur mobile**, ouvrir l'app
2. **Cliquer 3 fois rapidement** sur le titre "Scan2Sheet"
3. Cela devrait ouvrir la console (si Chrome Developer Tools est connecté)

**OU** utiliser Chrome Remote Debugging :

1. **Sur PC** : Ouvrir Chrome → `chrome://inspect`
2. **Sur mobile** : Activer le débogage USB (Paramètres → Options développeur)
3. **Connecter** le câble USB
4. **Sur PC**, cliquer "Inspect" sur votre app
5. **Console**, taper :

```javascript
diagPWA()
```

Résultat :
```
🔍 DIAGNOSTIC PWA
==================================================

📦 Service Worker:
  ✓ Enregistré: true
  ✓ Actif: true
  ✓ En attente: false
  ✓ En installation: false

📌 Version:
  ✓ Actuelle: v6

💾 Caches: 3
  • scan2sheet-static-v6
  • scan2sheet-api-v6
  • scan2sheet-images-v6

📄 Fichiers critiques:
  ✓ pwa-update.js: ✅ En cache

🔄 État mise à jour:
  ✓ updateAvailable: false
  ✓ newServiceWorker: false
  ✓ registration: true

==================================================
💡 Commandes utiles:
  • checkPWAUpdate() - Forcer une vérification
  • Cliquer sur le badge "⟳ v6" pour vérifier
==================================================
```

**Si vous voyez "Actuelle: v6"**, suivez l'Étape 2a pour déployer v7.

---

## 🚨 Méthode GARANTIE : Test complet from scratch

Si rien ne fonctionne, faisons un test complet propre :

### 1. Nettoyer le mobile

**Sur mobile** :
1. **Désinstaller** l'app de l'écran d'accueil (appui long → Supprimer)
2. **Chrome** → Menu → Historique → Effacer données de navigation → **Tout effacer**
3. **Fermer** Chrome (swipe up pour tuer l'app)
4. **Redémarrer** le téléphone (optionnel mais recommandé)

### 2. Déployer v6

**Sur PC** :
```bash
# S'assurer que c'est bien v6
grep CACHE_VERSION frontend/service-worker.js
# Doit afficher : const CACHE_VERSION = 'v6';

# Si c'est autre chose, modifier et :
git add frontend/service-worker.js
git commit -m "fix(pwa): ensure v6"
git push
```

**Attendre 3 minutes.**

### 3. Installer l'app sur mobile

**Sur mobile** :
1. **Ouvrir** Chrome
2. **Aller** sur votre URL (ex: https://votre-app.run.app)
3. **Menu** → "Installer l'application"
4. **Ajouter** à l'écran d'accueil
5. **Ouvrir** l'app depuis l'écran d'accueil
6. **Vérifier** le badge : doit afficher **`⟳ v6`**

### 4. Déployer v7

**Sur PC** :
```bash
# Modifier la version
sed -i "s/CACHE_VERSION = 'v6'/CACHE_VERSION = 'v7'/" frontend/service-worker.js

# Commit
git add frontend/service-worker.js
git commit -m "test(pwa): deploy v7 for notification test"

# Push
git push
```

**Attendre 3 minutes.**

### 5. Tester sur mobile

**Sur mobile** :
1. **L'app est toujours ouverte** (ne pas la fermer)
2. **Attendre** 60-90 secondes
3. **Regarder en haut de l'écran**
4. **La notification devrait apparaître** ! 🎉

```
┌──────────────────────────────────────────────┐
│ ✨ Nouvelle version disponible !             │
│ Cliquez sur "Actualiser" pour profiter des   │
│ dernières améliorations                      │
│ [Actualiser] [✕]                             │
└──────────────────────────────────────────────┘
```

5. **Cliquer** "Actualiser"
6. **L'app recharge**
7. **Le badge dit maintenant** **`⟳ v7`** ! ✅

---

## 🎯 Forcer la vérification manuellement (sans attendre)

### Méthode 1 : Cliquer sur le badge

**Sur mobile**, dans l'app :
1. **Cliquer** sur le badge bleu **`⟳ v6`** en haut
2. Le badge affiche **`⟳ ...`** (vérification en cours)
3. Si une nouvelle version existe → **Notification apparaît !**

### Méthode 2 : Console (Chrome DevTools)

**Avec Chrome Remote Debugging** :
```javascript
checkPWAUpdate()
```

---

## 🐛 Problème : "Rien ne se passe après 2 minutes"

### Checklist de vérification :

- [ ] Vous avez bien **déployé v7** (pas juste modifié en local)
- [ ] Vous avez **attendu 3 minutes** après le `git push`
- [ ] L'app mobile affiche **`⟳ v6`** (pas v7)
- [ ] Vous avez **attendu 60-90 secondes** avec l'app ouverte
- [ ] Vous n'avez **PAS** vidé le cache entre v6 et v7 (sinon pas de comparaison possible)

### Si tout est OK et toujours rien :

**Faire un diagnostic complet** :

1. **Sur PC**, vérifier que v7 est bien déployée :
   ```bash
   curl https://votre-app.run.app/service-worker.js | grep CACHE_VERSION
   # Doit afficher : const CACHE_VERSION = 'v7';
   ```

2. **Sur mobile avec Remote Debugging** :
   ```javascript
   diagPWA()
   ```

3. **Regarder** la ligne "En attente" :
   - Si `true` → Un nouveau SW est là mais pas activé → Cliquez sur le badge
   - Si `false` → Le mobile ne voit pas la nouvelle version

4. **Forcer la vérification** :
   ```javascript
   checkPWAUpdate()
   ```

5. **Regarder les logs** dans la console :
   ```
   [PWA Update] Vérification des mises à jour...
   [PWA Update] 🆕 Mise à jour trouvée !
   ```

---

## ✅ Checklist : "Ça marche !"

Vous saurez que ça fonctionne quand :

- [ ] Le badge affiche **`⟳ v6`** sur mobile
- [ ] Vous déployez **v7** sur le serveur
- [ ] Après 60-90 secondes, **la notification apparaît**
- [ ] Vous cliquez **"Actualiser"**
- [ ] L'app recharge et le badge dit **`⟳ v7`**
- [ ] Dans la console : `[PWA Update] ✅ Nouveau Service Worker installé !`

---

## 📞 Commandes de debug utiles

```javascript
// 1. Diagnostic complet
diagPWA()

// 2. Vérifier version actuelle
navigator.serviceWorker.getRegistration().then(r =>
  fetch(r.active.scriptURL).then(resp => resp.text()).then(t =>
    console.log('Version:', t.match(/CACHE_VERSION = '(.+?)'/)?.[1])
  )
);

// 3. Forcer vérification
checkPWAUpdate()

// 4. Voir tous les caches
caches.keys().then(k => console.log('Caches:', k))

// 5. Vérifier s'il y a un SW en attente
navigator.serviceWorker.getRegistration().then(r =>
  console.log('Waiting:', r.waiting, 'Installing:', r.installing)
);
```

---

## 🎬 TL;DR - Version ultra-courte

```bash
# Sur PC
echo "const CACHE_VERSION = 'v7';" > /tmp/version.txt
# Modifier ligne 10 de frontend/service-worker.js avec v7
git add frontend/service-worker.js
git commit -m "test: v7"
git push

# Attendre 3 minutes

# Sur mobile
# 1. Ouvrir l'app (qui a v6)
# 2. Attendre 90 secondes
# 3. BOUM ! Notification apparaît 🎉
# 4. Cliquer "Actualiser"
# 5. Badge dit maintenant v7 ✅
```

---

**Questions ?** Tapez `diagPWA()` dans la console pour un diagnostic complet !
