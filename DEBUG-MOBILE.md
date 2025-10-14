# 🐛 Debug Mobile - Notification ne s'affiche pas

## 🔍 Diagnostic rapide

### Méthode 1 : Chrome Remote Debugging (RECOMMANDÉ)

**Sur PC :**
1. Ouvrir Chrome
2. Aller sur `chrome://inspect`
3. Activer "Discover USB devices"

**Sur mobile Android :**
1. Paramètres → À propos du téléphone
2. Appuyer 7 fois sur "Numéro de build"
3. Retour → Options développeur
4. Activer "Débogage USB"
5. Connecter le câble USB au PC

**Sur PC (dans chrome://inspect) :**
1. Votre téléphone devrait apparaître
2. Accepter sur le téléphone
3. Trouver votre app dans la liste
4. Cliquer "Inspect"
5. Console s'ouvre !

**Dans la console :**
```javascript
// 1. Diagnostic complet
diagPWA()

// 2. Forcer vérification
checkPWAUpdate()

// 3. Voir les logs en temps réel
// Regarder si vous voyez :
// [PWA Update] Vérification des mises à jour...
// [PWA Update] 🆕 Mise à jour trouvée !
```

---

## 🔍 Problèmes courants sur mobile

### Problème 1 : Le service worker ne détecte pas la mise à jour

**Symptômes :**
- Desktop : notification apparaît ✅
- Mobile : rien ne se passe ❌

**Diagnostic :**
```javascript
navigator.serviceWorker.getRegistration().then(reg => {
  console.log('Active:', reg.active);
  console.log('Waiting:', reg.waiting);  // ← Devrait être truthy si mise à jour dispo
  console.log('Installing:', reg.installing);
});
```

**Solutions :**
```javascript
// Forcer l'update
navigator.serviceWorker.getRegistration().then(reg => {
  reg.update();
});

// Attendre 5 secondes puis re-vérifier
setTimeout(() => {
  navigator.serviceWorker.getRegistration().then(reg => {
    if (reg.waiting) {
      console.log('✅ Mise à jour détectée !');
      // Forcer l'affichage de la notification
      checkPWAUpdate();
    }
  });
}, 5000);
```

### Problème 2 : La notification est hors écran

**Symptômes :**
- La notification existe mais n'est pas visible

**Diagnostic :**
```javascript
// Vérifier si la notification existe dans le DOM
document.getElementById('pwa-update-notification')
// Devrait retourner un élément HTML ou null
```

**Solution :**
Si elle existe, elle est peut-être cachée. Forcer l'affichage :
```javascript
const notif = document.getElementById('pwa-update-notification');
if (notif) {
  notif.style.position = 'fixed';
  notif.style.top = '20px';
  notif.style.left = '50%';
  notif.style.transform = 'translateX(-50%)';
  notif.style.zIndex = '99999';
}
```

### Problème 3 : iOS Safari bloque les notifications

**Symptômes :**
- Fonctionne sur Android
- Ne fonctionne pas sur iPhone

**Solution :**
Sur iOS, les notifications custom (DOM) fonctionnent, mais avec des limitations :
```javascript
// Vérifier le navigateur
const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
console.log('iOS:', isIOS);

if (isIOS) {
  // Sur iOS, s'assurer que la notification est dans le viewport
  window.scrollTo(0, 0);
}
```

### Problème 4 : Le cache mobile n'est pas vidé

**Symptômes :**
- Vous avez déployé v7
- Le mobile a toujours v6
- Pas de détection de mise à jour

**Solution :**
```javascript
// Vérifier la version du SW actif
navigator.serviceWorker.getRegistration().then(reg => {
  fetch(reg.active.scriptURL).then(r => r.text()).then(text => {
    const version = text.match(/CACHE_VERSION = '(.+?)'/)[1];
    console.log('Version SW actuelle:', version);
  });
});

// Si c'est toujours v6 alors que vous avez déployé v7 :
// 1. Vider le cache
caches.keys().then(keys =>
  Promise.all(keys.map(k => caches.delete(k)))
);

// 2. Désinstaller le SW
navigator.serviceWorker.getRegistrations().then(regs =>
  Promise.all(regs.map(r => r.unregister()))
);

// 3. Recharger
location.reload();
```

---

## 🧪 Test forcé de la notification

Si vous voulez juste **voir** la notification sans attendre :

```javascript
// Forcer l'affichage de la notification (même sans mise à jour)
const testNotif = document.createElement('div');
testNotif.innerHTML = `
  <div style="
    position: fixed;
    top: 16px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 14px 16px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    z-index: 99999;
    max-width: 90vw;
    box-sizing: border-box;
  ">
    <div style="font-weight: bold; margin-bottom: 8px;">
      ✨ Test de notification !
    </div>
    <div style="font-size: 13px; margin-bottom: 12px;">
      Si vous voyez ceci, les notifications fonctionnent.
    </div>
    <button onclick="this.closest('div').parentElement.remove()" style="
      background: white;
      color: #667eea;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: bold;
      width: 100%;
    ">
      Fermer
    </button>
  </div>
`;
document.body.appendChild(testNotif);
```

**Si cette notification s'affiche :** Le problème n'est pas le CSS mais la détection de mise à jour.

**Si cette notification NE s'affiche PAS :** Problème JavaScript ou console mobile.

---

## 📱 Checklist complète

Cochez au fur et à mesure :

### Vérifications basiques
- [ ] Le mobile a Internet
- [ ] L'app est ouverte (pas en arrière-plan)
- [ ] Vous avez attendu 60-90 secondes
- [ ] Le badge affiche bien "⟳ v6" (pas v7)
- [ ] Le serveur a bien v7 déployée

### Vérifications techniques
- [ ] `diagPWA()` fonctionne dans la console
- [ ] `checkPWAUpdate()` ne retourne pas d'erreur
- [ ] Le SW est enregistré (`navigator.serviceWorker.controller` existe)
- [ ] Le fichier `pwa-update.js` est chargé

### Tests
- [ ] Le test forcé de notification ci-dessus fonctionne
- [ ] Cliquer sur le badge "⟳ v6" fait quelque chose
- [ ] Les logs `[PWA Update]` apparaissent dans la console

---

## 🚀 Solution rapide : Réinstaller proprement

Si rien ne fonctionne, réinitialiser complètement :

### Sur mobile :

1. **Désinstaller l'app**
   - Appui long sur l'icône
   - Supprimer de l'écran d'accueil

2. **Vider le cache Chrome**
   - Chrome → Menu (⋮) → Historique
   - Effacer les données de navigation
   - Cocher "Tout"
   - Effacer

3. **Fermer Chrome**
   - Swipe up et tuer Chrome

4. **Redémarrer le téléphone** (optionnel mais recommandé)

### Sur PC :

```bash
# S'assurer que v7 est déployée
git log -1 --oneline
git push

# Attendre 3 minutes
```

### Sur mobile :

1. **Ouvrir Chrome**
2. **Aller sur votre URL**
3. **Menu → Installer l'application**
4. **Ouvrir l'app**
5. **Vérifier le badge : doit dire "⟳ v7"**

### Puis tester la mise à jour :

```bash
# Sur PC
sed -i "s/CACHE_VERSION = 'v7'/CACHE_VERSION = 'v8'/" frontend/service-worker.js
git add frontend/service-worker.js
git commit -m "test: v8"
git push

# Attendre 3 minutes

# Sur mobile (app ouverte)
# - Attendre 60 secondes
# - Notification devrait apparaître !
```

---

## 💡 Logs à surveiller

Dans la console, vous devriez voir (dans l'ordre) :

```
[PWA Update] Initialisation du gestionnaire de mises à jour...
[PWA Update] ✅ Gestionnaire initialisé
[PWA Update] ✅ Listeners configurés
[PWA Update] Registration obtenue
[PWA Update] Vérification des mises à jour...
[PWA Update] Version affichée: v6
```

Puis après 60 secondes :
```
[PWA Update] Vérification périodique...
```

Puis quand v7 est détectée :
```
[PWA Update] 🆕 Mise à jour trouvée !
[PWA Update] État du nouveau SW: installing
[PWA Update] État du nouveau SW: installed
[PWA Update] ✅ Nouveau Service Worker installé !
[PWA Update] Affichage de la notification de mise à jour: nouvelle version
```

**Si vous ne voyez pas ces logs :** Le script `pwa-update.js` ne s'exécute pas.

---

## 📞 Informations à fournir pour debug

Si ça ne fonctionne toujours pas, donnez-moi :

1. **Résultat de `diagPWA()`**
2. **Version affichée sur le badge** (⟳ v?)
3. **Navigateur utilisé** (Chrome, Firefox, Safari)
4. **Système** (Android, iOS)
5. **Logs de la console** (copier-coller)
6. **Description du problème visuel** (badge mal placé ? notification coupée ? rien ne s'affiche ?)

---

**Bon courage ! 💪**
