# ====== Config ======
COMPOSE ?= docker compose
FILE    ?= infra/docker-compose.yml
PROJ    ?= receipt

# Raccourci
DC = $(COMPOSE) -f $(FILE) -p $(PROJ)

# Configuration locale
.PHONY: setup
setup:
	@echo "🔧 Configuration initiale..."
	@if [ ! -f "infra/.env" ]; then \
		cp infra/.env.example infra/.env; \
		echo "✅ Fichier infra/.env créé depuis infra/.env.example"; \
		echo "⚠️  Veuillez remplir les variables dans infra/.env"; \
	else \
		echo "✅ Fichier infra/.env existe déjà"; \
	fi
	@echo "📁 Créez le répertoire backend/keys/ et placez votre sa-key.json dedans"
	@echo "🚀 Ensuite lancez: make up"

# ====== Cibles ======
.PHONY: help up down restart ps logs install sh-app lint check-quality format build-assets cache-bust deploy-staging deploy-prod smoke-test smoke-test-staging smoke-test-prod check-deployment test-docker setup-gcp-secrets test-cloudbuild

help:
	@echo "📋 Commandes disponibles :"
	@echo ""
	@echo "🔧 Configuration :"
	@echo "  make setup         -> configuration initiale (.env, structure)"
	@echo ""
	@echo "🐳 Docker :"
	@echo "  make up            -> démarrer les containers en arrière-plan"
	@echo "  make down          -> arrêter et supprimer les containers/réseaux"
	@echo "  make restart       -> redémarrer la stack"
	@echo "  make ps            -> état des services"
	@echo "  make logs          -> logs suivis (app)"
	@echo "  make install       -> composer install dans le service 'app'"
	@echo "  make sh-app        -> shell dans le conteneur 'app'"
	@echo ""
	@echo "🚀 Build & Assets :"
	@echo "  make build-assets   -> build des assets avec cache-busting"
	@echo "  make cache-bust     -> cache-busting automatique (recommandé)"
	@echo "  make deploy-staging -> déploiement staging avec cache-busting"
	@echo "  make deploy-prod    -> déploiement production avec cache-busting"
	@echo ""
	@echo "🧪 Tests :"
	@echo "  make smoke-test    -> tests de smoke locaux"
	@echo "  make smoke-test-staging -> tests de smoke sur staging"
	@echo "  make smoke-test-prod -> tests de smoke sur production"
	@echo "  make check-deployment -> vérifier l'état du déploiement Cloud Run"
	@echo "  make test-docker   -> tester le build Docker localement"
	@echo "  make test-cloudbuild -> test du cloudbuild.yaml localement"
	@echo ""
	@echo "🔍 Qualité de code :"
	@echo "  make lint          -> linter le code (JS + PHP)"
	@echo "  make check-quality -> vérifier la qualité du code"
	@echo "  make format        -> formater le code"
	@echo ""
	@echo "🔐 GCP Secrets :"
	@echo "  make setup-gcp-secrets -> configurer les secrets dans GCP Secret Manager"

# --- Docker Compose ---
up:
	$(DC) up -d

down:
	$(DC) down

restart: down up

ps:
	$(DC) ps

logs:
	$(DC) logs -f app

# Commande simple pour démarrer
dev:
	$(DC) up -d
	@echo "🚀 Application démarrée sur http://localhost:8080"
	@echo "📊 Vérifier les logs: make logs"
	@echo "🛑 Arrêter: make down"

# --- Composer (dans le service 'app') ---
# On utilise 'run' pour ne pas exiger que le conteneur soit déjà démarré.
install:
	$(DC) run --rm --no-deps app \
		composer install --no-interaction --prefer-dist --optimize-autoloader

# Shell dans le conteneur app (essaie bash puis sh)
sh-app:
	-$(DC) exec app bash -lc 'cd /var/www/html && exec bash' || \
	$(DC) exec app sh -lc 'cd /var/www/html && exec sh'

# --- Qualité de code ---
lint:
	@echo "🔍 Linting JavaScript..."
	@$(DC) exec app eslint /var/www/html/assets/js/app.js --config /var/www/html/../.eslintrc.js
	@echo "🔍 Linting PHP..."
	@$(DC) exec app /root/.config/composer/vendor/bin/phpcs --standard=/var/www/html/../phpcs.xml /var/www/html/

check-quality:
	@echo "🔍 Vérification de la qualité du code..."
	@./scripts/check-docs-simple.sh

format:
	@echo "🎨 Formatage du code..."
	@echo "🔧 Formatage JavaScript..."
	@$(DC) exec app eslint /var/www/html/assets/js/app.js --config /var/www/html/../.eslintrc.js --fix
	@echo "✅ JavaScript formaté"
	@echo "🔧 Formatage PHP..."
	@$(DC) exec app /root/.config/composer/vendor/bin/phpcbf --standard=/var/www/html/../phpcs.xml /var/www/html/
	@echo "✅ PHP formaté"

# --- Déploiement ---

smoke-test:
	@echo "🧪 Tests de smoke locaux..."
	@echo "Testing http://localhost:8080..."
	@curl -f http://localhost:8080/ || (echo "❌ Home page failed" && exit 1)
	@curl -f http://localhost:8080/api/config || (echo "❌ API config failed" && exit 1)
	@echo "✅ Local smoke tests passed"

smoke-test-staging:
	@echo "🧪 Tests de smoke sur staging..."
	@SERVICE_URL=$$(gcloud run services describe receipt-parser --region=europe-west9 --format='value(status.url)' 2>/dev/null || echo "https://receipt-parser-staging-264113083582.a.run.app"); \
	echo "Testing $$SERVICE_URL"; \
	curl -f $$SERVICE_URL/ || (echo "❌ Staging home page failed" && exit 1); \
	curl -f $$SERVICE_URL/api/config || (echo "❌ Staging API config failed" && exit 1); \
	echo "✅ Staging smoke tests passed"

smoke-test-prod:
	@echo "🧪 Tests de smoke sur production..."
	@SERVICE_URL=$$(gcloud run services describe receipt-parser --region=europe-west9 --format='value(status.url)' 2>/dev/null || echo "https://receipt-parser-264113083582.a.run.app"); \
	echo "Testing $$SERVICE_URL"; \
	curl -f $$SERVICE_URL/ || (echo "❌ Production home page failed" && exit 1); \
	curl -f $$SERVICE_URL/api/config || (echo "❌ Production API config failed" && exit 1); \
	echo "✅ Production smoke tests passed"

check-deployment:
	@./scripts/check-deployment-status.sh

test-docker:
	@./scripts/test-docker-build.sh


# --- Assets ---
build-assets:
	@echo "📦 Build des assets avec cache-busting..."
	@./scripts/build-assets.sh

cache-bust:
	@echo "🔄 Cache-busting automatique..."
	@./scripts/cache-bust-safe.sh

deploy-staging:
	@echo "🚀 Déploiement staging avec cache-busting..."
	@./scripts/deploy-with-cache-bust.sh staging

deploy-prod:
	@echo "🚀 Déploiement production avec cache-busting..."
	@./scripts/deploy-with-cache-bust.sh production

setup-gcp-secrets:
	@echo "🔐 Configuration des secrets dans GCP Secret Manager..."
	@./scripts/setup-gcp-secrets.sh

test-cloudbuild:
	@echo "🧪 Test du cloudbuild.yaml localement..."
	@./scripts/test-cloudbuild-locally.sh
