# 🔧 Troubleshooting - Receipt API

## 🚨 Problèmes de déploiement Cloud Run

### **Erreur : "Container failed to start and listen on the port"**

**Symptôme complet :**
```
ERROR: (gcloud.run.deploy) Revision 'receipt-parser-xxx' is not ready and cannot serve traffic. 
The user-provided container failed to start and listen on the port defined provided by the 
PORT=8080 environment variable within the allocated timeout.
```

**Causes possibles :**

1. **Apache ne démarre pas correctement**
   - Script `start.sh` échoue
   - Configuration Apache invalide
   - Port mal configuré

2. **Fichiers manquants dans l'image Docker**
   - `.htaccess` non copié
   - `index.php` manquant
   - Frontend non présent

3. **Timeout trop court**
   - Le conteneur met trop de temps à démarrer
   - Cloud Run abandonne avant qu'Apache soit prêt

4. **Erreur dans le Dockerfile**
   - Dépendances manquantes
   - Permissions incorrectes
   - Composer échoue

**Solutions appliquées :**

#### ✅ **1. Amélioration du script de démarrage (`infra/docker/start.sh`)**
```bash
# Vérification des fichiers critiques avant démarrage
echo "🔍 Checking critical files..."
if [ ! -f "/var/www/html/.htaccess" ]; then
    echo "❌ .htaccess not found!"
    exit 1
fi

# Configuration robuste du port
echo "Listen $PORT" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Vérification de la configuration Apache
if apache2ctl configtest 2>&1 | grep -i "syntax ok"; then
    echo "✅ Apache configuration OK"
else
    echo "❌ Apache configuration error:"
    apache2ctl configtest
    exit 1
fi
```

#### ✅ **2. Augmentation du timeout Cloud Run (`cloudbuild.yaml`)**
```yaml
- '--timeout'
- '300'  # 5 minutes au lieu du défaut (60s)
- '--no-cpu-throttling'  # Éviter le throttling CPU
```

#### ✅ **3. Optimisation du Dockerfile**
```dockerfile
# Copier composer.json en premier pour utiliser le cache Docker
COPY backend/composer.json backend/composer.lock /var/www/html/

# Installer les dépendances
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copier le code ensuite (sans vendor/)
COPY backend/*.php /var/www/html/
COPY frontend/ /var/www/html/frontend/
COPY .htaccess /var/www/html/.htaccess
```

#### ✅ **4. Création d'un `.dockerignore`**
Exclure les fichiers inutiles du build :
```
backend/vendor
backend/keys/*.json
node_modules
.git
*.md
scripts/
```

**Vérification après déploiement :**

```bash
# Vérifier les logs de démarrage
gcloud logging read "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser" \
  --project=scan-document-ai \
  --limit=100 \
  --format="table(timestamp,textPayload)"

# Rechercher les erreurs
gcloud logging read "resource.type=cloud_run_revision AND 
  severity>=ERROR" \
  --project=scan-document-ai \
  --limit=50
```

---

## 🔴 Problèmes de secrets

### **Erreur : "Secret was not found"**

**Symptôme :**
```
ERROR: spec.template.spec.containers[0].env[9].value_from.secret_key_ref.name: 
Secret projects/264113083582/secrets/allowed-emails/versions/latest was not found
```

**Cause :** Les secrets n'existent pas dans Google Secret Manager

**Solution :**
```bash
# Créer les secrets manquants
make setup-gcp-secrets

# Ou via Cloud Shell
echo -n "polmickael3@gmail.com" | gcloud secrets create allowed-emails \
  --data-file=- \
  --replication-policy="automatic" \
  --project=scan-document-ai

# Donner les permissions au Service Account
gcloud secrets add-iam-policy-binding allowed-emails \
  --member="serviceAccount:docai-sa@scan-document-ai.iam.gserviceaccount.com" \
  --role="roles/secretmanager.secretAccessor" \
  --project=scan-document-ai
```

**Vérification :**
```bash
# Lister tous les secrets
gcloud secrets list --project=scan-document-ai

# Vérifier les permissions d'un secret
gcloud secrets get-iam-policy allowed-emails --project=scan-document-ai
```

---

## ❌ Problèmes de routage (403, 404)

### **Erreur 403 Forbidden sur `/`**

**Cause :** `.htaccess` non copié dans l'image Docker ou Apache ne lit pas le `.htaccess`

**Solution :**
1. Vérifier que `.htaccess` est copié dans le Dockerfile :
   ```dockerfile
   COPY .htaccess /var/www/html/.htaccess
   ```

2. Vérifier qu'Apache autorise `.htaccess` :
   ```apache
   <Directory /var/www/html>
       AllowOverride All  # ← Important !
       Require all granted
   </Directory>
   ```

3. Vérifier que `mod_rewrite` est activé :
   ```dockerfile
   RUN a2enmod rewrite headers ssl
   ```

**Test :**
```bash
# Vérifier que .htaccess est présent dans le conteneur
docker run --rm -it [IMAGE] ls -la /var/www/html/.htaccess

# Ou sur Cloud Run (via Cloud Shell)
gcloud run services proxy receipt-parser --region=europe-west9
# Puis dans un autre terminal
curl http://localhost:8080/
```

### **Erreur 404 sur `/api/config`**

**Cause :** Routage `.htaccess` incorrect ou `index.php` manquant

**Solution :**
Vérifier les règles de réécriture dans `.htaccess` :
```apache
# API routes vers index.php
RewriteRule ^api/(.*)$ index.php [QSA,L]
```

**Test :**
```bash
curl -v https://[SERVICE_URL]/api/config
# Devrait retourner un JSON, pas du HTML
```

---

## 🧪 Smoke tests échouent

### **Erreur : "gcloud: command not found" dans smoke tests**

**Cause :** Utilisation de l'image `gcr.io/cloud-builders/curl` qui ne contient pas `gcloud`

**Solution :**
```yaml
# cloudbuild.yaml
- name: 'gcr.io/cloud-builders/gcloud'  # ← Pas 'curl'
  id: 'smoke-tests'
```

### **Smoke tests retournent 000**

**Cause :** Service pas encore démarré ou URL incorrecte

**Solution :**
1. Ajouter un délai avant les tests :
   ```bash
   echo "⏳ Waiting for service to be ready..."
   sleep 10
   ```

2. Augmenter le nombre de tentatives :
   ```bash
   for i in {1..5}; do  # 5 au lieu de 3
     status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 30 "$url")
     if [ "$status_code" = "200" ]; then
       return 0
     fi
     sleep 10  # Attendre plus longtemps entre les tentatives
   done
   ```

---

## 📊 Commandes de diagnostic

### **Vérifier l'état du service Cloud Run**
```bash
make check-deployment

# Ou manuellement
gcloud run services describe receipt-parser \
  --region=europe-west9 \
  --project=scan-document-ai
```

### **Voir les logs en temps réel**
```bash
gcloud logging tail "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser" \
  --project=scan-document-ai
```

### **Voir les dernières erreurs**
```bash
gcloud logging read "resource.type=cloud_run_revision AND 
  resource.labels.service_name=receipt-parser AND 
  severity>=ERROR" \
  --project=scan-document-ai \
  --limit=50 \
  --format="table(timestamp,severity,textPayload)"
```

### **Tester le conteneur localement**
```bash
# Build l'image
cd infra
docker build -t receipt-parser-test -f Dockerfile ..

# Lancer le conteneur
docker run --rm -p 8080:8080 \
  -e PORT=8080 \
  -e APP_ENV=local \
  receipt-parser-test

# Tester
curl http://localhost:8080/
curl http://localhost:8080/api/config
```

### **Vérifier les révisions Cloud Run**
```bash
# Lister les révisions
gcloud run revisions list \
  --service=receipt-parser \
  --region=europe-west9 \
  --project=scan-document-ai

# Voir les détails d'une révision spécifique
gcloud run revisions describe receipt-parser-[SHA] \
  --region=europe-west9 \
  --project=scan-document-ai
```

### **Rollback vers une révision précédente**
```bash
# Rediriger 100% du trafic vers une révision spécifique
gcloud run services update-traffic receipt-parser \
  --to-revisions=receipt-parser-[OLD_SHA]=100 \
  --region=europe-west9 \
  --project=scan-document-ai
```

---

## 🔍 Checklist de diagnostic

Quand un déploiement échoue, suivez cette checklist :

- [ ] **Vérifier les logs Cloud Build**
  ```bash
  # Voir le dernier build
  gcloud builds list --limit=1 --project=scan-document-ai
  
  # Voir les logs d'un build
  gcloud builds log [BUILD_ID] --project=scan-document-ai
  ```

- [ ] **Vérifier que l'image est bien créée**
  ```bash
  gcloud artifacts docker images list \
    europe-west9-docker.pkg.dev/scan-document-ai/apps-ticket/receipt-parser \
    --limit=5
  ```

- [ ] **Vérifier les secrets**
  ```bash
  gcloud secrets list --project=scan-document-ai
  ```

- [ ] **Vérifier les permissions du Service Account**
  ```bash
  gcloud projects get-iam-policy scan-document-ai \
    --flatten="bindings[].members" \
    --filter="bindings.members:docai-sa@scan-document-ai.iam.gserviceaccount.com"
  ```

- [ ] **Tester localement**
  ```bash
  make up
  make smoke-test
  ```

- [ ] **Vérifier les logs de démarrage Cloud Run**
  ```bash
  gcloud logging read "resource.type=cloud_run_revision AND 
    textPayload=~'Starting Receipt API'" \
    --project=scan-document-ai \
    --limit=10
  ```

---

## 📞 Support

Si le problème persiste après avoir suivi ce guide :

1. **Consulter les logs détaillés**
   ```bash
   gcloud logging read "resource.type=cloud_run_revision" \
     --project=scan-document-ai \
     --limit=200 \
     --format=json > logs.json
   ```

2. **Vérifier la documentation Google Cloud Run**
   - https://cloud.google.com/run/docs/troubleshooting

3. **Tester avec une image minimale**
   Créer un Dockerfile simple pour isoler le problème :
   ```dockerfile
   FROM php:8.1-apache
   RUN echo "<?php phpinfo();" > /var/www/html/index.php
   EXPOSE 8080
   CMD ["apache2-foreground"]
   ```

4. **Contacter le support GCP**
   - https://cloud.google.com/support

