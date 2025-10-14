# 🧪 Guide de test - Système de mise à jour PWA

## ✅ Corrections appliquées

Les problèmes suivants ont été corrigés :

1. ✅ **pwa-update.js manquant du cache** - Ajouté à `STATIC_ASSETS`
2. ✅ **Détection trop passive** - Vérification immédiate + périodique (60s)
3. ✅ **Pas de gestionnaire SKIP_WAITING** - Ajouté dans le service worker
4. ✅ **Version incrémentée** - `v5` → `v6`
5. ✅ **Logs détaillés** - Pour déboguer facilement

---

## 📋 ÉTAPE 1 : Test en local (obligatoire avant déploiement)

### 1.1 Démarrer l'application

```bash
# Lancer Docker
docker compose up

# Ou si déjà lancé, redémarrer
docker compose restart
```

### 1.2 Ouvrir dans le navigateur

```
http://localhost:8080
```

### 1.3 Ouvrir la console (F12)

**Vous DEVEZ voir ces logs :**

```
[SW] Installing new version v6...
[SW] Version v6 installed, waiting to activate...
[SW] Activating version v6...
[SW] Version v6 activated!
[PWA Update] Initialisation du gestionnaire de mises à jour...
[PWA Update] ✅ Gestionnaire initialisé - Tapez checkPWAUpdate() pour forcer une vérification
[PWA Update] ✅ Listeners configurés
[PWA Update] Registration obtenue
[PWA Update] Vérification des mises à jour...
```

✅ **Si vous voyez ces logs** : Le système est opérationnel !
❌ **Si vous ne voyez rien** : Vérifiez que le service worker est bien enregistré (voir section Debugging)

---

## 📋 ÉTAPE 2 : Simuler une mise à jour en local

### 2.1 Avec l'application ouverte

1. **Ouvrez** `service-worker.js`
2. **Changez** la version :
   ```javascript
   const CACHE_VERSION = 'v7'; // v6 → v7
   ```
3. **Sauvegardez** le fichier
4. **Dans la console**, tapez :
   ```javascript
   checkPWAUpdate()
   ```

**Résultat attendu dans la console :**

```
[PWA Update] Vérification manuelle demandée
[PWA Update] Vérification des mises à jour...
[PWA Update] 🆕 Mise à jour trouvée !
[SW] Installing new version v7...
[PWA Update] État du nouveau SW: installing
[PWA Update] État du nouveau SW: installed
[PWA Update] ✅ Nouveau Service Worker installé !
[PWA Update] Affichage de la notification de mise à jour: nouvelle version
```

**Résultat visuel :**

Une notification devrait apparaître en haut de l'écran :

```
┌──────────────────────────────────────────────┐
│ ✨ Nouvelle version disponible !             │
│ Cliquez sur "Actualiser" pour profiter des   │
│ dernières améliorations                      │
│ [Actualiser] [✕]                             │
└──────────────────────────────────────────────┘
```

### 2.2 Cliquer sur "Actualiser"

**Résultat attendu :**

```
[PWA Update] Utilisateur a cliqué sur Actualiser
[PWA Update] Envoi de SKIP_WAITING au nouveau SW
[SW] Message received: {type: 'SKIP_WAITING'}
[SW] SKIP_WAITING requested, activating immediately...
[SW] Activating version v7...
[PWA Update] Changement de contrôleur détecté
[PWA Update] Rechargement automatique...
```

La page se recharge avec la nouvelle version v7 !

---

## 📋 ÉTAPE 3 : Tester sur mobile (après déploiement)

### 3.1 Déployer la v6

```bash
git add .
git commit -m "fix(pwa): correction système de mise à jour

- Ajouter pwa-update.js au cache du SW
- Améliorer détection (immédiate + périodique 60s)
- Ajouter gestionnaire SKIP_WAITING
- Incrémenter version v5 → v6

🤖 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

git push
```

**Attendre ~2-5 minutes** pour que Cloud Build déploie.

### 3.2 Sur votre téléphone

1. **Ouvrir** l'application PWA (depuis l'écran d'accueil)

2. **Vérifier la version actuelle** :
   - Ouvrir la console mobile (si possible)
   - Ou noter le comportement actuel

3. **Fermer complètement** l'app (swipe up pour kill)

### 3.3 Déployer une nouvelle version (v7)

**Sur votre PC :**

```bash
# Modifier service-worker.js
# Changer: const CACHE_VERSION = 'v7';

git add frontend/service-worker.js
git commit -m "test(pwa): test notification v7"
git push
```

**Attendre ~2-5 minutes**

### 3.4 Sur mobile - Ré-ouvrir l'app

1. **Ouvrir** l'app depuis l'écran d'accueil
2. **Attendre 10-60 secondes**
3. **La notification devrait apparaître** :

```
┌────────────────────────────────────┐
│ ✨ Nouvelle version disponible !  │
│ Cliquez sur "Actualiser"           │
│ [Actualiser] [✕]                   │
└────────────────────────────────────┘
```

4. **Cliquer sur "Actualiser"**
5. **L'app se recharge** avec la v7 !

---

## 🐛 DEBUGGING : Si ça ne marche toujours pas

### Debug 1 : Vérifier le Service Worker

**Console (F12) :**

```javascript
// Vérifier la registration
navigator.serviceWorker.getRegistration().then(reg => {
  console.log('Registration:', reg);
  console.log('Active:', reg.active);
  console.log('Waiting:', reg.waiting);
  console.log('Installing:', reg.installing);
});
```

**Résultat attendu :**

```
Registration: ServiceWorkerRegistration {active: ServiceWorker, ...}
Active: ServiceWorker {scriptURL: "http://localhost:8080/service-worker.js", state: "activated"}
Waiting: null
Installing: null
```

### Debug 2 : Forcer une vérification

**Console :**

```javascript
checkPWAUpdate()
```

**Si la fonction n'existe pas :**

❌ Le script `pwa-update.js` n'est pas chargé !

**Vérifier :**

```javascript
// Dans la console
document.querySelector('script[src*="pwa-update"]')
```

**Devrait retourner :**

```html
<script src="assets/js/pwa-update.js"></script>
```

### Debug 3 : Vider complètement le cache

**Chrome Desktop :**

1. F12 → Application
2. Service Workers → **Unregister**
3. Clear Storage → **Clear site data**
4. Recharger (Ctrl+Shift+R)

**Chrome Mobile :**

1. Chrome → Menu (3 points)
2. Historique → Effacer les données de navigation
3. Avancé → Tout effacer
4. Redémarrer Chrome

**iOS Safari :**

1. Réglages → Safari
2. Avancé → Données de sites web
3. **Supprimer toutes les données**
4. Fermer Safari (swipe up)
5. Ré-ouvrir Safari

### Debug 4 : Vérifier le cache

**Console :**

```javascript
caches.keys().then(keys => {
  console.log('Caches:', keys);
  keys.forEach(key => {
    caches.open(key).then(cache => {
      cache.keys().then(requests => {
        console.log(`Cache ${key}:`, requests.map(r => r.url));
      });
    });
  });
});
```

**Vérifier que `pwa-update.js` est dans le cache :**

```
Cache scan2sheet-static-v6: [
  "http://localhost:8080/",
  "http://localhost:8080/index.html",
  "http://localhost:8080/assets/js/app.js",
  "http://localhost:8080/assets/js/pwa-update.js",  ← DOIT ÊTRE LÀ !
  ...
]
```

### Debug 5 : Mode verbose

**Dans la console, tapez :**

```javascript
// Activer tous les logs
localStorage.setItem('debug', '*');

// Recharger
location.reload();
```

---

## 🎯 Checklist de vérification complète

Avant de dire "ça ne marche pas", vérifiez :

- [ ] Le service worker est bien enregistré (F12 → Application → Service Workers)
- [ ] La console affiche `[PWA Update] ✅ Gestionnaire initialisé`
- [ ] Le fichier `pwa-update.js` existe dans `/frontend/assets/js/`
- [ ] Le fichier est référencé dans `index.html` : `<script src="assets/js/pwa-update.js">`
- [ ] Le fichier est dans le cache du SW (voir Debug 4)
- [ ] La version est bien `v6` (ou supérieure)
- [ ] Vous avez déployé sur Cloud Build (pas juste en local)
- [ ] Vous avez attendu 2-5 minutes après le push
- [ ] Vous avez fermé ET ré-ouvert l'app mobile (pas juste refresh)
- [ ] Vous avez attendu au moins 60 secondes après ouverture

---

## 📱 Scénarios de test sur mobile

### Scénario A : Notification immédiate

1. **App mobile ouverte** avec version v6
2. **Vous déployez** version v7
3. **Attendre 60 secondes** (vérification périodique)
4. **Notification apparaît** automatiquement
5. **Cliquer "Actualiser"** → v7 !

### Scénario B : Notification au prochain démarrage

1. **App mobile fermée**
2. **Vous déployez** version v7
3. **Ré-ouvrir l'app**
4. **Attendre 5-10 secondes**
5. **Notification apparaît**
6. **Cliquer "Actualiser"** → v7 !

### Scénario C : Forcer manuellement (développeurs)

1. **Ouvrir l'app**
2. **Console mobile** (Chrome Remote Debugging)
3. **Taper** : `checkPWAUpdate()`
4. **Notification apparaît** immédiatement

---

## 🚨 Problèmes courants et solutions

### "Je ne vois toujours rien sur mobile"

**Vérifier sur desktop d'abord :**

```bash
# Ouvrir http://localhost:8080
# F12 → Console
# Chercher [PWA Update]
```

Si ça marche en local mais pas sur mobile :

1. **Vider le cache mobile** complètement
2. **Désinstaller** la PWA de l'écran d'accueil
3. **Réinstaller** depuis le navigateur
4. **Attendre 60-90 secondes**

### "La notification apparaît en boucle"

**Solution :**

```javascript
// Console
sessionStorage.clear();
localStorage.clear();
location.reload();
```

### "J'ai l'erreur 'checkPWAUpdate is not defined'"

❌ Le fichier `pwa-update.js` n'est pas chargé.

**Vérifier :**

1. Le fichier existe : `/frontend/assets/js/pwa-update.js`
2. Il est dans `index.html` : `<script src="assets/js/pwa-update.js">`
3. Il est dans le cache du SW
4. Recharger en Ctrl+Shift+R

### "La page se recharge en boucle"

C'est le flag anti-boucle qui bug.

**Solution :**

```javascript
sessionStorage.removeItem('pwa-reloading');
location.reload();
```

---

## 🎓 Comprendre le système

### Cycle de vie d'une mise à jour

```
1. Vous pushez v7
   ↓
2. Cloud Build déploie
   ↓
3. Mobile charge index.html (toujours la dernière version)
   ↓
4. index.html charge pwa-update.js (toujours la dernière version)
   ↓
5. pwa-update.js vérifie s'il y a un nouveau service-worker.js
   ↓
6. OUI ! service-worker.js v7 est disponible
   ↓
7. Installation en arrière-plan
   ↓
8. État = "installed" → NOTIFICATION s'affiche
   ↓
9. User clique "Actualiser"
   ↓
10. Message SKIP_WAITING envoyé
   ↓
11. SW v7 devient actif
   ↓
12. Page se recharge automatiquement
   ↓
13. App fonctionne avec v7 !
```

### Pourquoi 60 secondes ?

Le script vérifie automatiquement toutes les 60 secondes s'il y a une nouvelle version. C'est un bon compromis entre :

- ⚡ Réactivité (pas trop long)
- 🔋 Batterie (pas trop fréquent)
- 📡 Bande passante (pas trop de requêtes)

Vous pouvez modifier cette durée dans `pwa-update.js` ligne 247 :

```javascript
// Vérifier toutes les 30 secondes (au lieu de 60)
}, 30 * 1000);
```

---

## 📞 Besoin d'aide ?

### Logs à fournir

Si vous demandez de l'aide, fournissez :

1. **Logs de la console** (F12) avec le filtre `[SW]` et `[PWA Update]`
2. **Version actuelle** : Tapez `CACHE_VERSION` dans la console du SW
3. **État du SW** : Résultat de `navigator.serviceWorker.getRegistration()`
4. **Contenu du cache** : Résultat de `caches.keys()`

### Commandes utiles

```javascript
// Version actuelle
navigator.serviceWorker.getRegistration().then(r =>
  fetch(r.active.scriptURL).then(resp => resp.text()).then(t =>
    console.log(t.match(/CACHE_VERSION = '(.+?)'/)[1])
  )
);

// Forcer mise à jour
navigator.serviceWorker.getRegistration().then(r => r.update());

// Tout réinitialiser
caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
navigator.serviceWorker.getRegistrations().then(regs =>
  Promise.all(regs.map(r => r.unregister()))
);
```

---

## ✅ Test final : Procédure complète

**Pour être 100% sûr que ça marche :**

1. **Local - Test initial**
   ```bash
   docker compose up
   # Ouvrir http://localhost:8080
   # F12 → Chercher [PWA Update] ✅
   ```

2. **Local - Test mise à jour**
   ```javascript
   // Changer CACHE_VERSION v6 → v7
   // Console : checkPWAUpdate()
   // Notification apparaît ✅
   ```

3. **Déploiement v6**
   ```bash
   git commit -m "fix(pwa): v6"
   git push
   # Attendre 3 min
   ```

4. **Mobile - Installation**
   ```
   # Ouvrir depuis navigateur
   # "Ajouter à l'écran d'accueil"
   # Ouvrir depuis l'écran d'accueil ✅
   ```

5. **Déploiement v7**
   ```bash
   # Changer CACHE_VERSION v6 → v7
   git commit -m "test: v7"
   git push
   # Attendre 3 min
   ```

6. **Mobile - Test notification**
   ```
   # Fermer l'app (swipe up)
   # Attendre 10 secondes
   # Ré-ouvrir l'app
   # Attendre 60 secondes
   # Notification apparaît ! ✅
   # Cliquer "Actualiser"
   # App recharge avec v7 ! ✅
   ```

**Si TOUTES les étapes passent** → Le système fonctionne parfaitement ! 🎉

---

**Dernière mise à jour :** 2025-10-14
**Version du système :** v6
