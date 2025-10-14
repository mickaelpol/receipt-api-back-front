# 🚀 Améliorations PWA - Scan2Sheet

## ✨ Résumé des changements

Ce document décrit les améliorations apportées à la Progressive Web App (PWA) pour gérer les splashscreens et les mises à jour automatiques.

---

## 📱 1. Splashscreens et icônes

### Problème initial
- Le `manifest.json` et `service-worker.js` référençaient des fichiers PNG qui n'existaient pas
- Seuls des fichiers SVG étaient disponibles dans `/frontend/assets/icons/`

### Solution appliquée
- ✅ Correction des références dans `service-worker.js` pour utiliser les SVG existants
- ✅ Ajout de meta tags iOS pour améliorer l'expérience PWA sur iPhone/iPad
- ✅ Création d'un script `generate-icons.js` pour générer des PNG si nécessaire

### Comment fonctionne le splashscreen ?

#### Sur Android (12+)
- Le système génère **automatiquement** le splashscreen à partir de :
  - L'icône `maskable` du manifest.json
  - La couleur `background_color` du manifest.json

#### Sur iOS (13+)
- iOS génère le splashscreen à partir de :
  - L'icône Apple Touch Icon
  - La couleur de thème
  - Les meta tags `apple-mobile-web-app-*`

#### Personnalisation avancée (optionnel)

Si vous souhaitez des **splashscreens personnalisés** avec votre logo en PNG :

```bash
# Installer sharp pour la conversion SVG → PNG
cd frontend
npm install sharp

# Générer les PNG
node generate-icons.js
```

Cela créera automatiquement :
- `icon-192.png`
- `icon-512.png`
- `icon-192-maskable.png`
- `icon-512-maskable.png`
- `apple-touch-icon.png` (180x180)

---

## 🔄 2. Système de mise à jour automatique

### Problème initial
- Les mises à jour de l'app n'étaient pas visibles immédiatement sur mobile
- L'utilisateur ne savait pas qu'une nouvelle version était disponible
- Le cache pouvait garder d'anciennes versions

### Solution appliquée

#### A. Service Worker amélioré (`service-worker.js`)
- ✅ Incrémentation de `CACHE_VERSION` à `v5`
- ✅ Logs détaillés lors de l'installation et activation
- ✅ Notification automatique aux clients lors d'une mise à jour
- ✅ Nettoyage automatique des anciens caches

#### B. Système de notification (`pwa-update.js`)
- ✅ Détection automatique des nouvelles versions
- ✅ Affichage d'une belle notification à l'utilisateur
- ✅ Bouton "Actualiser" pour appliquer immédiatement
- ✅ Vérification périodique toutes les 5 minutes

### Comment fonctionne la mise à jour ?

1. **Vous déployez** une nouvelle version sur Cloud Build
2. **Le service worker** détecte automatiquement la nouvelle version
3. **L'utilisateur voit** une notification en haut de l'écran :
   ```
   ✨ Nouvelle version disponible !
   Une mise à jour est prête à être installée (v5)
   [Actualiser] [✕]
   ```
4. **L'utilisateur clique** sur "Actualiser"
5. **La page se recharge** avec la nouvelle version

---

## 🛠️ 3. Workflow de déploiement

### Pour appliquer une mise à jour :

1. **Modifiez votre code** (backend ou frontend)

2. **Incrémentez la version** du cache dans `service-worker.js` :
   ```javascript
   const CACHE_VERSION = 'v6'; // Incrémenter à chaque déploiement
   ```

3. **Commitez et pushez** vers Cloud Build :
   ```bash
   git add .
   git commit -m "feat: nouvelle fonctionnalité"
   git push
   ```

4. **Cloud Build déploie** automatiquement

5. **Les utilisateurs reçoivent** la notification de mise à jour

### ⚠️ Important

**À CHAQUE DÉPLOIEMENT** :
- Incrémentez `CACHE_VERSION` dans `service-worker.js`
- Sinon, les utilisateurs ne verront pas les changements !

Exemple de versionnement :
```javascript
v5 → v6 → v7 → v8...
```

Ou avec des tags sémantiques :
```javascript
'v1.0.0' → 'v1.0.1' → 'v1.1.0' → 'v2.0.0'
```

---

## 🧪 4. Comment tester localement

### Tester les splashscreens

**Sur Android :**
1. Ouvrez Chrome → Visitez votre app
2. Menu → "Installer l'application"
3. Fermez l'app
4. Ré-ouvrez depuis l'écran d'accueil
5. → Vous verrez le splashscreen

**Sur iOS :**
1. Ouvrez Safari → Visitez votre app
2. Bouton partage → "Sur l'écran d'accueil"
3. Fermez l'app
4. Ré-ouvrez depuis l'écran d'accueil
5. → Vous verrez le splashscreen

### Tester les mises à jour

1. **Première installation** :
   ```bash
   # Terminal 1 : Lancer l'app en local
   docker compose up
   ```

2. **Ouvrir dans le navigateur** :
   ```
   http://localhost:8080
   ```

3. **Faire une modification** :
   - Changez `CACHE_VERSION = 'v5'` en `CACHE_VERSION = 'v6'`
   - Rechargez la page (Ctrl+Shift+R pour forcer)

4. **Vérifier la console** :
   ```
   [SW] Installing new version v6...
   [SW] Version v6 activated!
   [PWA] Nouvelle version détectée: v6
   ```

5. **Vérifier la notification** :
   - Une notification devrait apparaître en haut
   - Cliquez sur "Actualiser"

---

## 📊 5. Monitoring et debugging

### Logs du service worker

Ouvrez la **Console développeur** (F12) :

```javascript
// Vérifier la version actuelle
navigator.serviceWorker.getRegistration().then(reg => {
  console.log('Service Worker:', reg);
});

// Forcer une mise à jour
navigator.serviceWorker.getRegistration().then(reg => {
  reg.update();
});
```

### Vider le cache manuellement

**Sur Chrome** :
1. F12 → Application
2. Service Workers → Unregister
3. Clear Storage → Clear site data

**Sur iOS Safari** :
1. Réglages → Safari
2. Avancé → Données de sites web
3. Supprimer les données

---

## 🎨 6. Personnalisation du splashscreen

### Modifier les couleurs

Dans `manifest.json` :
```json
{
  "background_color": "#ffffff",  ← Couleur de fond du splash
  "theme_color": "#1A73E8"        ← Couleur de la barre de statut
}
```

### Modifier les icônes

1. Remplacez `icon-master.svg` par votre logo
2. Régénérez les icônes :
   ```bash
   cd frontend
   node generate-icons.js
   ```

---

## 🐛 7. Problèmes courants

### "Je ne vois pas la nouvelle version sur mobile"

**Solutions** :
1. Vérifiez que `CACHE_VERSION` a été incrémentée
2. Forcez la fermeture de l'app (swipe up)
3. Ré-ouvrez l'app
4. Attendez 5 minutes (vérification auto)
5. Ou : Désintallez et réinstallez l'app

### "Le splashscreen ne s'affiche pas"

**Solutions** :
1. Sur iOS : Assurez-vous d'avoir ajouté l'app à l'écran d'accueil
2. Sur Android : Vérifiez que l'app est en mode standalone
3. Vérifiez que les icônes existent dans `/assets/icons/`

### "La notification de mise à jour apparaît en boucle"

**Solution** :
- Videz le cache et rechargez complètement
- Vérifiez qu'il n'y a pas d'erreur dans la console

---

## 📚 Références

- [Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Web App Manifest](https://web.dev/add-manifest/)
- [iOS PWA Support](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html)
- [Android Splash Screens](https://web.dev/articles/customize-install#creating_your_own_splash_screen)

---

## ✅ Checklist de déploiement

Avant chaque déploiement :

- [ ] Incrémenté `CACHE_VERSION` dans `service-worker.js`
- [ ] Testé localement (console sans erreurs)
- [ ] Commité avec un message clair
- [ ] Pushez vers Cloud Build
- [ ] Attendez le déploiement (~2-5 min)
- [ ] Testez sur mobile (forcez fermeture + réouverture)
- [ ] Vérifiez la notification de mise à jour

---

**Questions ?** Consultez les logs dans la console navigateur (F12) avec le filtre `[SW]` ou `[PWA]`.
