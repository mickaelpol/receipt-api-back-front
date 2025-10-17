# 🔄 Guide : Forcer la mise à jour de la PWA sur Android

## Pourquoi ma PWA n'affiche pas les nouvelles icônes/splash screens ?

Android et Chrome **cachent agressivement** les PWA pour des raisons de performance. Même après réinstallation, l'ancien manifest et les anciennes icônes peuvent rester en cache.

## ✅ Solution : Méthode complète de mise à jour

### Étape 1 : Désinstaller complètement la PWA

1. **Désinstaller depuis l'écran d'accueil**
   - Appuyez longuement sur l'icône de Scan2Sheet
   - Sélectionnez "Désinstaller" ou "Supprimer"

2. **Vider le cache de Chrome** (IMPORTANT !)
   - Ouvrez Chrome
   - Menu (⋮) → Paramètres
   - Confidentialité et sécurité → Effacer les données de navigation
   - Sélectionnez **"Période : Toutes les périodes"**
   - Cochez :
     - ✅ Images et fichiers en cache
     - ✅ Cookies et données de site
   - Appuyez sur "Effacer les données"

### Étape 2 : Forcer le rechargement du site

1. **Ouvrir le site dans Chrome**
   ```
   https://votre-url-de-production.run.app
   ```

2. **Forcer le rechargement sans cache**
   - Appuyez sur l'icône de rechargement
   - OU : Menu (⋮) → Actualiser

3. **Vérifier le Service Worker**
   - Dans Chrome, allez à : `chrome://serviceworker-internals/`
   - Cherchez votre domaine
   - Cliquez sur "Unregister" si l'ancien service worker est présent
   - Retournez sur votre site et rechargez

### Étape 3 : Vérifier le manifest avant installation

1. **Ouvrir les DevTools dans Chrome Android** (facultatif mais recommandé)
   - Connectez votre téléphone à votre PC via USB
   - Sur PC : `chrome://inspect/#devices`
   - Inspectez votre site
   - Onglet "Application" → "Manifest"
   - Vérifiez que les icônes PNG sont présentes (192, 512, 1024)

2. **Ou vérifier manuellement**
   - Allez sur : `https://votre-url/manifest.json`
   - Vous devez voir les icônes PNG et les couleurs `#4388ff` et `#0b0f14`

### Étape 4 : Réinstaller la PWA

1. **Installer depuis Chrome**
   - Menu (⋮) → "Installer l'application"
   - OU : Bannière "Ajouter à l'écran d'accueil" si elle apparaît

2. **Vérifier l'icône**
   - L'icône sur l'écran d'accueil doit être nouvelle
   - Si c'est encore l'ancienne, recommencez depuis l'Étape 1

### Étape 5 : Tester le splash screen

1. **Fermer complètement l'app** (swipe depuis les apps récentes)
2. **Lancer l'app depuis l'écran d'accueil**
3. **Observer le splash screen**
   - Vous devriez voir :
     - Fond noir bleuté (#0b0f14)
     - Votre nouvelle icône au centre (avec masque adaptatif)
     - Barre d'état bleue (#4388ff)

## 🐛 Dépannage avancé

### Problème : Splash screen toujours absent

**Cause possible :** Android ne génère le splash screen que si :
- L'icône est au format PNG
- Le manifest a `background_color` et `theme_color`
- L'icône est déclarée avec `purpose: "maskable"`

**Solution :**
```bash
# Vérifier le manifest en ligne
curl https://votre-url/manifest.json | grep -E "background_color|theme_color|maskable"
```

Vous devez voir :
```json
"background_color": "#0b0f14",
"theme_color": "#4388ff",
"purpose": "maskable"
```

### Problème : Icône reste carrée (pas adaptative)

**Cause :** Android n'utilise pas l'icône maskable

**Solution :**
1. Vérifiez que le manifest contient des entrées séparées :
   ```json
   { "purpose": "any" }
   { "purpose": "maskable" }
   ```
2. Testez votre icône sur https://maskable.app/editor
3. Assurez-vous que le contenu important est dans la "safe zone" (80% du centre)

### Problème : Cache têtu (rien ne fonctionne)

**Solution nucléaire :**

1. **Désinstaller la PWA**
2. **Effacer TOUTES les données de Chrome**
   - Paramètres Android → Applications → Chrome
   - Stockage → Effacer les données (pas juste le cache !)
   - ⚠️ Cela vous déconnectera de tous les sites
3. **Redémarrer le téléphone**
4. **Réinstaller la PWA**

## 📱 Version du Service Worker

Vous pouvez voir la version actuelle du service worker dans l'app :
- Badge bleu en haut : `⟳ v2.0.0`
- Si vous voyez `v1.0.1` ou moins, le service worker n'est pas à jour

**Comment forcer la mise à jour du service worker :**
1. Ouvrez l'app (déjà installée)
2. Tirez vers le bas pour rafraîchir (pull-to-refresh)
3. Attendez 5-10 secondes
4. Le badge devrait afficher `v2.0.0`
5. Fermez et relancez l'app

## ✅ Checklist finale

Avant de dire que ça ne fonctionne pas, vérifiez :

- [ ] La PWA a été **complètement désinstallée** (pas juste cachée)
- [ ] Le **cache de Chrome a été vidé** (toutes les périodes)
- [ ] Le site a été **rechargé sans cache**
- [ ] Le **manifest.json** est accessible et contient les bonnes icônes
- [ ] Les **icônes PNG existent** : /assets/icons/icon-512x512.png
- [ ] Le **service worker est v2.0.0** (badge ⟳ dans l'app)
- [ ] L'app a été **fermée complètement** avant de la relancer
- [ ] Le téléphone a un **Android récent** (8.0+)

## 🚀 Après la mise à jour réussie

Vous devriez voir :
- ✅ Nouvelle icône sur l'écran d'accueil (adaptative)
- ✅ Splash screen au lancement (fond noir bleuté + icône + barre bleue)
- ✅ Badge `⟳ v2.0.0` dans l'app
- ✅ Icône adaptée à la forme de votre appareil (cercle, arrondi, etc.)

## 📞 Support

Si après toutes ces étapes ça ne fonctionne toujours pas :
1. Envoyez une capture d'écran de `chrome://serviceworker-internals/`
2. Envoyez le contenu de `/manifest.json` depuis votre navigateur
3. Indiquez la version d'Android et de Chrome
4. Décrivez précisément ce que vous voyez vs ce que vous attendez
