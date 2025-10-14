# GitHub Actions - DÉSACTIVÉ

## ⚠️ Important

GitHub Actions a été **désactivé** pour ce projet en raison de problèmes de facturation.

**Fichier désactivé** : `tests.yml.disabled`

---

## ✅ Alternative : Google Cloud Build

La CI/CD est maintenant gérée par **Google Cloud Build**.

**Avantages** :
- ✅ GRATUIT : 120 minutes/jour
- ✅ Pas de problème de facturation
- ✅ Tests complets : PHP, JavaScript, Docker
- ✅ Déploiement automatique sur Cloud Run
- ✅ Smoke tests post-déploiement

---

## 📚 Documentation

Voir : [`CI-CD-DOCUMENTATION.md`](../../CI-CD-DOCUMENTATION.md)

---

## 🔄 Réactiver GitHub Actions

Si vous souhaitez réactiver GitHub Actions plus tard :

```bash
# Renommer le fichier
mv .github/workflows/tests.yml.disabled .github/workflows/tests.yml

# Commit
git add .github/workflows/tests.yml
git commit -m "chore: réactiver GitHub Actions"
git push
```

⚠️ **Attention** : Assurez-vous que votre compte GitHub n'a plus de problèmes de facturation avant de réactiver.

---

## 💡 Recommandation

**Google Cloud Build** est recommandé pour ce projet car il est déjà configuré pour le déploiement sur Cloud Run et offre 120 minutes gratuites par jour.
