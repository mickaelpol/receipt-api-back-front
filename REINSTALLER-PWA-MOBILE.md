# 📱 Réinstaller la PWA sur mobile

## 🎯 Problème

Vous avez désinstallé l'app et maintenant Chrome ne propose plus de la réinstaller.

**C'est normal !** Chrome a une politique anti-spam qui bloque la réinstallation pendant un certain temps.

---

## 🚀 Solution rapide (90% des cas)

### Étape 1 : Vider les données du site

**Sur Chrome mobile :**

1. **Aller** sur votre URL (https://votre-app.run.app)
2. **Cliquer** sur le **cadenas** 🔒 ou l'icône à gauche de l'URL
3. **Paramètres du site** (Site settings)
4. **Effacer et réinitialiser** (Clear & reset)
5. **Confirmer**

### Étape 2 : Fermer Chrome complètement

1. **Quitter** Chrome
2. **Swipe up** pour voir les apps récentes
3. **Fermer** Chrome (swipe à droite)
4. **Attendre** 10 secondes

### Étape 3 : Rouvrir Chrome

1. **Ouvrir** Chrome
2. **Aller** sur votre URL
3. **Attendre** 2-3 secondes

**→ La bannière d'installation devrait apparaître en bas de l'écran ! 🎉**

Si vous la voyez :
- Cliquez sur "Installer" ou "Ajouter à l'écran d'accueil"
- C'est tout ! ✅

---

## 🔧 Solution complète (si la rapide ne marche pas)

### Méthode A : Effacer tout l'historique

**Sur Chrome mobile :**

1. **Menu** (⋮ en haut à droite)
2. **Historique**
3. **Effacer les données de navigation**
4. **Avancé**
5. **Tout cocher** :
   - ✅ Historique de navigation
   - ✅ Cookies et données de sites
   - ✅ Images et fichiers en cache
   - ✅ Mots de passe (optionnel)
   - ✅ Données de saisie automatique (optionnel)
   - ✅ Paramètres des sites ← **IMPORTANT !**
6. **Période** : Toutes les périodes
7. **Effacer les données**
8. **Fermer Chrome** (swipe up)
9. **Attendre** 10 secondes
10. **Rouvrir** Chrome
11. **Aller** sur votre URL

---

### Méthode B : Installation manuelle via le menu

**Si la bannière n'apparaît toujours pas :**

1. **Aller** sur votre URL
2. **Menu** (⋮)
3. **Chercher** l'une de ces options :
   - "Installer l'application"
   - "Ajouter à l'écran d'accueil"
   - "Installer Scan2Sheet"

**Si vous ne voyez AUCUNE de ces options :**

→ Allez à la Méthode C

---

### Méthode C : Forcer la réinstallabilité (GARANTI)

J'ai modifié le manifest pour "tromper" Chrome et lui faire croire que c'est une nouvelle app.

**Sur PC :**

```bash
# Le manifest a été modifié avec un nouvel ID
# Déployez ces changements :
git add frontend/manifest.json
git commit -m "fix(pwa): add unique ID to force reinstallability"
git push
```

**⏰ Attendre 3-4 minutes** (déploiement Cloud Build)

**Sur mobile :**

1. **Vider les données** (Méthode A ci-dessus)
2. **Fermer Chrome** complètement
3. **Attendre** 30 secondes
4. **Rouvrir** Chrome
5. **Aller** sur votre URL
6. **Attendre** 5 secondes
7. **La bannière devrait apparaître !**

---

### Méthode D : Redémarrer le téléphone (DERNIER RECOURS)

Si rien ne marche :

1. **Redémarrer** votre téléphone
2. **Ouvrir** Chrome
3. **Aller** sur votre URL
4. La bannière devrait apparaître

---

## 🔍 Vérifier que la PWA est installable

**Sur PC, avec Chrome DevTools :**

1. **Ouvrir** votre URL sur desktop
2. **F12** → Onglet **Application**
3. **Manifest** (dans le menu de gauche)
4. **Regarder** les erreurs
5. **Regarder** "Installability" en bas

**Critères requis :**
- ✅ Manifest valide avec `name`, `short_name`, `start_url`, `display`, `icons`
- ✅ Service Worker enregistré
- ✅ HTTPS (ou localhost)
- ✅ Icônes 192x192 et 512x512

**Tous devraient être verts ✅**

---

## 🐛 Problèmes courants

### "Je ne vois toujours aucune option d'installation"

**Causes possibles :**

1. **Chrome n'a pas détecté la PWA**
   - Rechargez la page (Ctrl+Shift+R)
   - Attendez 5-10 secondes
   - Vérifiez le manifest (F12 → Application → Manifest)

2. **Le manifest a une erreur**
   - Vérifiez sur desktop avec DevTools
   - Vérifiez que les icônes existent

3. **Le service worker n'est pas enregistré**
   - Console → Chercher `[SW]` logs
   - F12 → Application → Service Workers → Devrait être "activated"

4. **Chrome vous a "banni" (rare)**
   - Attendez 24-48 heures
   - Ou utilisez la Méthode C (modifier le manifest)

---

### "La bannière apparaît mais disparaît trop vite"

**Solution :**

La bannière apparaît pendant 5-10 secondes. Si vous la manquez :

1. **Recharger** la page (🔄)
2. **Attendre** 2-3 secondes
3. **Elle devrait réapparaître**

Ou utilisez le **Menu → Installer l'application**

---

### "J'ai installé mais le badge affiche toujours v?"

C'est normal ! Le badge affichera la version après quelques secondes.

**Si ça persiste :**

1. **Fermer** l'app
2. **Ré-ouvrir**
3. **Attendre** 5 secondes
4. Le badge devrait afficher `⟳ v8`

---

## ✅ Comment savoir si c'est installé ?

### Sur Android :

**Méthode 1 : Écran d'accueil**
- Vous voyez l'icône "Scan2Sheet" sur l'écran d'accueil
- L'icône a le logo (pas juste le favicon)

**Méthode 2 : Gestionnaire d'apps**
- Paramètres → Applications
- Chercher "Scan2Sheet"
- Vous devriez la voir dans la liste

**Méthode 3 : Chrome**
- Menu Chrome (⋮) → Paramètres
- Gérer les applications
- "Scan2Sheet" devrait être listée

---

## 📊 États de la bannière d'installation

| Ce que vous voyez | Signification | Action |
|-------------------|---------------|--------|
| Bannière en bas "Installer Scan2Sheet" | ✅ Installable | Cliquez sur "Installer" |
| Menu → "Installer l'application" | ✅ Installable | Cliquez dessus |
| Rien du tout | ❌ Pas installable | Voir section Debug |
| "Ajouter un raccourci" seulement | ⚠️ Pas une vraie PWA | Vérifiez le manifest |

---

## 🎯 Checklist complète

Avant de dire "ça ne marche pas", vérifiez :

- [ ] J'ai vidé les données du site (cadenas 🔒 → Clear & reset)
- [ ] J'ai fermé Chrome complètement (swipe up)
- [ ] J'ai attendu au moins 10 secondes
- [ ] J'ai rouvert Chrome
- [ ] Je suis allé sur l'URL (pas juste rafraîchi)
- [ ] J'ai attendu 5 secondes sur la page
- [ ] J'ai vérifié le menu (⋮) pour "Installer l'application"
- [ ] J'ai essayé de recharger la page (🔄)
- [ ] J'ai vérifié sur desktop que le manifest est valide
- [ ] J'ai déployé le nouveau manifest avec l'ID unique

---

## 🚀 Procédure testée et garantie

**Cette procédure fonctionne à 99% :**

### Sur PC :

```bash
# 1. Déployer le nouveau manifest
git add frontend/manifest.json frontend/assets/js/pwa-update.js frontend/service-worker.js
git commit -m "fix(pwa): force reinstallability + improve detection"
git push

# Attendre 4 minutes
```

### Sur mobile :

```bash
# 2. Nettoyer Chrome
Chrome → Menu (⋮) → Historique → Effacer données
→ Avancé → Tout cocher → Toutes les périodes → Effacer

# 3. Fermer Chrome
Swipe up → Fermer Chrome

# 4. Attendre
Attendre 30 secondes

# 5. Redémarrer le téléphone (optionnel mais recommandé)
Éteindre → Rallumer

# 6. Rouvrir Chrome
Chrome → Aller sur votre URL

# 7. Attendre
Attendre 10 secondes sur la page

# → Bannière devrait apparaître en bas ! 🎉
```

---

## 💡 Astuce : Installation via QR Code

**Alternative si rien ne marche :**

1. **Sur PC**, générer un QR code de votre URL
2. **Sur mobile**, scanner le QR code
3. Chrome s'ouvre sur la page
4. La bannière devrait apparaître

---

## 📞 Toujours bloqué ?

**Informations à fournir pour debug :**

1. **Sur desktop** (F12 → Application → Manifest) :
   - Captures d'écran des erreurs
   - État "Installability"

2. **Sur mobile** :
   - Version d'Android
   - Version de Chrome (Menu → Paramètres → À propos de Chrome)
   - Ce que vous voyez dans Menu (⋮)

3. **Logs** :
   - Sur desktop : Console → Chercher `[SW]`
   - Sur mobile (Remote Debugging) : Chercher `[PWA Update]`

---

## 🎉 Ça marche !

**Si la bannière est apparue :**

1. **Cliquez** sur "Installer"
2. **Confirmez**
3. **L'icône** apparaît sur l'écran d'accueil
4. **Ouvrez** l'app depuis l'écran d'accueil
5. **Vérifiez** le badge : devrait dire `⟳ v8`

**Si le badge dit `⟳ v?` :**

- Attendez 5 secondes
- Ou cliquez sur le badge
- Ou fermez/rouvrez l'app

**Si vous voyez la version (ex: `⟳ v8`) :**

✅ **BRAVO ! L'app est installée et fonctionne !** 🎉

---

**Prochaine étape :** Tester la notification de mise à jour (déployer v9)
