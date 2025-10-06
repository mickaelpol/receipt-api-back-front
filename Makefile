# ====== Config ======
COMPOSE ?= docker compose
FILE    ?= infra/docker-compose.yml
PROJ    ?= receipt

# Raccourci
DC = $(COMPOSE) -f $(FILE) -p $(PROJ)

# ====== Cibles ======
.PHONY: help up down restart ps logs install sh-app lint check-quality format

help:
	@echo "📋 Commandes disponibles :"
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
	@echo "🧪 Tests :"
	@echo "  make smoke-test    -> tests de smoke locaux"
	@echo "  make smoke-test-staging -> tests de smoke sur staging"
	@echo "  make smoke-test-prod -> tests de smoke sur production"
	@echo "  make test-pipeline -> test du pipeline de déploiement"
	@echo ""
	@echo "🚀 Déploiement :"
	@echo "  make setup-deployment -> configurer GCP et triggers"
	@echo "  make deploy-staging -> déployer sur staging"
	@echo "  make deploy-prod -> déployer sur production"
	@echo "  make rollback-staging -> rollback staging"
	@echo "  make rollback-prod -> rollback production"
	@echo ""
	@echo "🎨 Assets :"
	@echo "  make generate-favicons -> générer les favicons"
	@echo "  make test-favicons -> tester les favicons"
	@echo ""
	@echo "🔍 Qualité de code :"
	@echo "  make lint          -> linter le code (JS + PHP)"
	@echo "  make check-quality -> vérifier la qualité du code"
	@echo "  make format        -> formater le code"

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
setup-gcp:
	@echo "🔧 Configuration des ressources GCP..."
	@./scripts/setup-gcp-resources.sh

setup-triggers:
	@echo "⚙️ Configuration des triggers Cloud Build..."
	@./scripts/setup-cloud-build-triggers.sh

setup-deployment: setup-gcp setup-triggers
	@echo "✅ Configuration du pipeline de déploiement terminée!"

test-pipeline:
	@echo "🧪 Test du pipeline de déploiement..."
	@./scripts/test-deployment-pipeline.sh

smoke-test:
	@echo "🧪 Tests de smoke locaux..."
	@./scripts/smoke-tests.sh http://localhost:8080

smoke-test-staging:
	@echo "🧪 Tests de smoke sur staging..."
	@./scripts/smoke-tests.sh https://receipt-parser-staging-$(shell gcloud config get-value project 2>/dev/null || echo "264113083582").a.run.app

smoke-test-prod:
	@echo "🧪 Tests de smoke sur production..."
	@./scripts/smoke-tests.sh https://receipt-parser-$(shell gcloud config get-value project 2>/dev/null || echo "264113083582").a.run.app

deploy-staging:
	@echo "🚀 Déploiement sur staging..."
	@gcloud builds triggers run scan2sheet-staging-deploy --branch=staging

deploy-prod:
	@echo "🚀 Déploiement sur production..."
	@gcloud builds triggers run scan2sheet-production-deploy --branch=main

rollback-staging:
	@echo "🔄 Rollback du déploiement staging..."
	@./scripts/rollback-deployment.sh staging

rollback-prod:
	@echo "🔄 Rollback du déploiement production..."
	@./scripts/rollback-deployment.sh production

# --- Assets ---
generate-favicons:
	@echo "🎨 Génération des favicons..."
	@./scripts/generate-favicons-node.js

test-favicons:
	@echo "🧪 Test des favicons..."
	@./scripts/test-favicons.sh