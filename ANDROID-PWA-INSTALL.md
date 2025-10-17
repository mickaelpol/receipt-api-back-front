# 📱 Guide complet : Installer la PWA Scan2Sheet sur Android

Ce guide vous explique **étape par étape** comment installer et voir vos nouvelles icônes et splash screens sur Android.

## ⚠️ IMPORTANT : Où testez-vous ?

**Votre téléphone Android doit être connecté à la même URL que votre développement local.**

### Option A : Testeren local (recommandé pour développement)
Votre téléphone doit accéder à `http://VOTRE-IP-LOCALE:8080`

**Trouver votre IP locale :**
```bash
# Sur Linux/WSL
hostname -I | awk '{print $1}'

# Ou
ip addr show | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | cut -d/ -f1 | head -1
```

Exemple : `http://192.168.1.100:8080`

### Option B : Tester en production
Si vous avez déployé sur Cloud Run, utilisez l'URL de production.

## 🔄 Procédure complète (OBLIGATOIRE)

### Étape 1 : Désinstaller l'ancienne PWA

1. **Désinstaller depuis l'écran d'accueil**
   - Appui long sur l'icône Scan2Sheet
   - Sélectionnez **"Désinstaller"** ou **"Supprimer"**
   - Confirmez

2. **Vérifier qu'elle est bien désinstallée**
   - Cherchez "Scan2Sheet" dans vos apps
   - Elle ne doit plus apparaître

### Étape 2 : Vider le cache de Chrome (CRUCIAL !)

1. **Ouvrir Chrome sur Android**

2. **Menu (⋮) → Paramètres**

3. **Confidentialité et sécurité → Effacer les données de navigation**

4. **Configurer :**
   - Période : **"Toutes les périodes"** ⚠️ Important !
   - Cocher :
     - ✅ Images et fichiers en cache
     - ✅ Cookies et données de site
   - NE PAS cocher :
     - ❌ Mots de passe (sauf si vous voulez)

5. **Appuyer sur "Effacer les données"**

6. **Attendre la fin du nettoyage**

### Étape 3 : Désinscrire l'ancien Service Worker

1. **Dans Chrome Android, aller à :**
   ```
   chrome://serviceworker-internals/
   ```

2. **Chercher** (Ctrl+F ou loupe) : `scan2sheet` ou `localhost` ou `votre-domaine`

3. **Pour chaque service worker trouvé :**
   - Cliquer sur **"Unregister"**
   - Confirmer

4. **Fermer l'onglet**

### Étape 4 : Accéder au site (NOUVELLE SESSION)

1. **Fermer TOUS les onglets Chrome**
   - Menu → Fermer tous les onglets

2. **Redémarrer Chrome**
   - Forcer l'arrêt de Chrome dans les paramètres Android
   - Relancer Chrome

3. **Nouvelle URL dans la barre d'adresse :**
   ```
   http://VOTRE-IP:8080
   ```

   Exemples :
   - Local : `http://192.168.1.100:8080`
   - Production : `https://votre-app.run.app`

4. **Attendre le chargement complet**

### Étape 5 : Vérifier le manifest AVANT d'installer

1. **Dans Chrome, aller manuellement à :**
   ```
   http://VOTRE-IP:8080/manifest.json
   ```

2. **Vous DEVEZ voir :**
   ```json
   {
     "background_color": "#0b0f14",
     "theme_color": "#4388ff",
     "icons": [
       {
         "src": "/assets/icons/icon-192x192.png",
         "purpose": "any"
       },
       {
         "src": "/assets/icons/icon-192x192.png",
         "purpose": "maskable"
       },
       ...
     ]
   }
   ```

3. **Si vous voyez encore l'ancien manifest :**
   - Le cache n'est pas vidé → recommencez l'Étape 2
   - Vous n'êtes pas sur la bonne URL → vérifiez l'URL

### Étape 6 : Installer la PWA

1. **Retourner sur la page d'accueil**
   ```
   http://VOTRE-IP:8080
   ```

2. **Attendre 2-3 secondes**

3. **Une bannière devrait apparaître : "Ajouter à l'écran d'accueil"**
   - Si elle n'apparaît pas : Menu (⋮) → **"Installer l'application"**

4. **Appuyer sur "Installer"** ou **"Ajouter"**

5. **Confirmer**

### Étape 7 : Vérifier l'icône

1. **Retourner à l'écran d'accueil**

2. **Regarder l'icône Scan2Sheet**

3. **Elle devrait être :**
   - ✅ Votre nouvelle icône PNG
   - ✅ Avec une forme adaptative (cercle ou arrondi selon votre appareil)

4. **Si c'est toujours l'ancienne icône :**
   - ❌ Vous n'avez pas vidé le cache → recommencez l'Étape 2
   - ❌ Vous testez sur une URL différente de celle où sont les nouvelles icônes

### Étape 8 : Tester le splash screen

1. **Fermer complètement l'app**
   - Bouton multitâche (carré)
   - Swiper l'app vers le haut pour la fermer

2. **Lancer l'app depuis l'écran d'accueil**

3. **Observer le splash screen pendant 1-2 secondes**

4. **Vous devriez voir :**
   - ✅ Fond noir bleuté (#0b0f14)
   - ✅ Votre icône au centre (avec masque adaptatif)
   - ✅ Barre d'état bleue (#4388ff)

5. **Si vous ne voyez PAS le splash :**
   - C'est normal si l'app se lance très vite (< 0.5s)
   - Android génère le splash seulement s'il y a un délai
   - Pour tester : Mettez votre téléphone en mode avion, lancez l'app (elle mettra plus de temps à charger)

## 🐛 Dépannage

### Problème : "Impossible de se connecter"

**Cause :** Votre téléphone n'est pas sur le même réseau WiFi

**Solution :**
1. Vérifier que PC et téléphone sont sur le même WiFi
2. Désactiver le pare-feu sur votre PC (temporairement)
3. Sur WSL : `ip addr show eth0` pour voir votre IP

### Problème : Manifest toujours ancien

**Cause :** Cache têtu de Chrome

**Solution nucléaire :**
1. Paramètres Android → Applications → Chrome
2. Stockage → **"Effacer les données"** (pas juste cache !)
   - ⚠️ Cela vous déconnectera de tous les sites
3. Redémarrer le téléphone
4. Recommencer depuis l'Étape 1

### Problème : Icône maskable pas appliquée

**Cause :** L'icône n'est pas dans la "safe zone" maskable

**Solution :**
1. Tester sur https://maskable.app/editor
2. Uploader `icon-512x512.png`
3. Vérifier que le contenu important est dans la zone bleue (80%)

### Problème : Splash screen absent

**Cause :** Android ne le génère que si :
- L'icône est PNG
- Le manifest a `background_color` et `theme_color`
- L'icône a `purpose: "maskable"`

**Vérification :**
```bash
curl http://VOTRE-IP:8080/manifest.json | grep -E "background_color|theme_color|maskable"
```

Doit afficher :
```
"background_color": "#0b0f14",
"theme_color": "#4388ff",
"purpose": "maskable"
```

## ✅ Checklist finale

Avant de dire que ça ne fonctionne pas, cochez :

- [ ] PWA désinstallée depuis l'écran d'accueil
- [ ] Cache de Chrome vidé (toutes les périodes)
- [ ] Service worker désinstallé (chrome://serviceworker-internals/)
- [ ] Chrome redémarré (force stop)
- [ ] Nouvelle session ouverte
- [ ] URL correcte testée (IP locale ou production)
- [ ] Manifest.json accessible et contient les bonnes icônes
- [ ] PWA réinstallée via la bannière ou le menu
- [ ] App fermée complètement avant de la relancer
- [ ] Splash screen observé au lancement (peut être très court !)

## 📊 Vérification rapide

**Test 1 : Manifest accessible**
```
http://VOTRE-IP:8080/manifest.json
→ Doit afficher JSON avec background_color: #0b0f14
```

**Test 2 : Icône accessible**
```
http://VOTRE-IP:8080/assets/icons/icon-512x512.png
→ Doit afficher votre nouvelle icône PNG
```

**Test 3 : Service Worker version**
```
Ouvrir l'app → Badge en haut à droite doit afficher "⟳ v1.0.3" ou plus
```

## 🎯 Résultat attendu

Après avoir suivi toutes ces étapes :

| Élément | Attendu |
|---------|---------|
| **Icône écran d'accueil** | Nouvelle icône PNG avec forme adaptative |
| **Splash screen** | Fond #0b0f14 + icône + barre #4388ff |
| **Onglet navigateur** | Nouvelle icône PNG dans l'onglet |
| **Badge version** | ⟳ v1.0.3 ou plus récent |

## 🆘 Support

Si après avoir suivi TOUTES ces étapes ça ne fonctionne toujours pas :

1. **Envoyez une capture d'écran de :**
   - L'URL que vous testez
   - Le contenu de `/manifest.json` depuis votre navigateur mobile
   - Le badge de version dans l'app (⟳ v?.?.?)
   - `chrome://serviceworker-internals/` filtré sur votre domaine

2. **Indiquez :**
   - Modèle de téléphone et version Android
   - Version de Chrome
   - URL exacte que vous utilisez (locale ou production)

3. **Décrivez ce que vous voyez vs ce que vous attendez**
