# Bootstrap 5.3.3 - Assets Locaux

## Politique des Source Maps

### Production
- **Source maps désactivées** : Les fichiers `.map` ne sont pas inclus
- **Raison** : Sécurité et performance en production
- **CSP** : Aucun appel externe autorisé

### Développement
- **Source maps locales** : Si nécessaire, servir depuis `assets/libs/`
- **Jamais de CDN** : Pas d'appels à `cdn.jsdelivr.net` ou autres CDNs
- **CSP** : Même politique stricte qu'en production

## Structure
```
frontend/assets/libs/bootstrap/5.3.3/
├── bootstrap.min.css          # CSS minifié
├── bootstrap.bundle.min.js     # JS minifié avec Popper
└── README.md                   # Cette documentation
```

## Mise à jour
1. Télécharger depuis https://getbootstrap.com/
2. Remplacer les fichiers dans ce dossier
3. Tester avec `./scripts/check-csp-violations.sh`
4. Vérifier que la CSP reste stricte

## Version
- **Bootstrap** : 5.3.3
- **Date d'ajout** : $(date)
- **Source** : https://getbootstrap.com/docs/5.3/
