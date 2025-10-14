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
.PHONY: help up down restart ps logs install sh-app lint check-quality format build-assets cache-bust deploy-staging deploy-prod deploy-direct smoke-test smoke-test-staging smoke-test-prod check-deployment test-docker install-hooks setup-gcp-secrets test-cloudbuild test test-coverage test-coverage-text test-unit test-integration

help:
	@echo "📋 Commandes disponibles :"
	@echo ""
	@echo "🔧 Configuration :"
	@echo "  make setup         -> configuration initiale (.env, structure)"
	@echo "  make install-hooks -> installer les Git hooks (pre-commit, pre-push)"
	@echo "  make setup-gcp-secrets -> configurer les secrets dans GCP Secret Manager"
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
	@echo "  make deploy-direct  -> déploiement direct vers Cloud Run (RECOMMANDÉ)"
	@echo "  make deploy-staging -> déploiement staging via GitHub Actions (manuel)"
	@echo "  make deploy-prod    -> déploiement production via GitHub Actions (manuel)"
	@echo ""
	@echo "🧪 Tests :"
	@echo "  make test          -> lancer tous les tests PHPUnit"
	@echo "  make test-unit     -> lancer uniquement les tests unitaires"
	@echo "  make test-integration -> lancer uniquement les tests d'intégration"
	@echo "  make test-coverage -> générer le rapport de couverture HTML"
	@echo "  make test-coverage-text -> afficher la couverture dans le terminal"
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
	@echo "📦 Installing dependencies..."
	$(DC) run --rm --no-deps app composer install --no-interaction --prefer-dist --optimize-autoloader --dev

# Shell dans le conteneur app (essaie bash puis sh)
sh-app:
	-$(DC) exec app bash -lc 'cd /var/www/html && exec bash' || \
	$(DC) exec app sh -lc 'cd /var/www/html && exec sh'

# --- Qualité de code ---
lint:
	@echo "🔍 Linting JavaScript..."
	@if command -v node > /dev/null 2>&1; then \
		node --check frontend/assets/js/app.js && echo "✅ JavaScript syntax OK"; \
	else \
		echo "⚠️  Node.js non installé, vérification JavaScript sautée"; \
	fi
	@echo "🔍 Linting PHP..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour le lint..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@if docker compose -f infra/docker-compose.yml -p receipt exec app test -f /var/www/html/vendor/bin/phpcs; then \
		docker compose -f infra/docker-compose.yml -p receipt exec app php -d memory_limit=512M /var/www/html/vendor/bin/phpcs --standard=phpcs.xml /var/www/html/ || echo "⚠️  PHPCS échoué"; \
	else \
		echo "⚠️  PHPCS non installé (lancez: make install)"; \
	fi
	@echo "🔍 Vérification de la syntaxe PHP..."
	@find backend -name "*.php" -not -path "*/vendor/*" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" || echo "✅ Syntaxe PHP OK"

check-quality:
	@echo "🔍 Vérification de la qualité du code..."
	@./scripts/check-docs-simple.sh

format:
	@echo "🎨 Formatage du code..."
	@echo "🔧 Formatage JavaScript..."
	@if command -v npx > /dev/null 2>&1; then \
		npx eslint frontend/assets/js/app.js --config .eslintrc.js --fix && echo "✅ JavaScript formaté"; \
	else \
		echo "⚠️  ESLint non installé, formatage JavaScript sauté"; \
		echo "💡 Pour installer: npm install -g eslint"; \
	fi
	@./scripts/format-php.sh

# --- Tests PHPUnit ---
test:
	@echo "🧪 Running PHPUnit tests..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour les tests..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@$(DC) exec app sh -c "cd /var/www/html && php vendor/bin/phpunit -c /phpunit.xml"

test-unit:
	@echo "🧪 Running unit tests..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour les tests..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@$(DC) exec app sh -c "cd /var/www/html && php vendor/bin/phpunit -c /phpunit.xml --testsuite Unit"

test-integration:
	@echo "🧪 Running integration tests..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour les tests..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@$(DC) exec app sh -c "cd /var/www/html && php vendor/bin/phpunit -c /phpunit.xml --testsuite Integration"

test-coverage:
	@echo "📊 Generating code coverage report..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour les tests..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@echo "🔧 Installing PCOV extension if needed..."
	@$(DC) exec app sh -c "pecl list | grep -q pcov || pecl install pcov || true"
	@$(DC) exec app sh -c "php -m | grep -q pcov || echo 'extension=pcov.so' > /usr/local/etc/php/conf.d/pcov.ini || true"
	@echo "🧪 Running tests with coverage..."
	@$(DC) exec app sh -c "cd /var/www/html && php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit -c /phpunit.xml --coverage-html coverage/html --coverage-clover coverage/clover.xml"
	@echo "✅ Coverage report generated in backend/coverage/html/index.html"
	@echo "💡 Ouvrez backend/coverage/html/index.html dans votre navigateur"

test-coverage-text:
	@echo "📊 Running tests with text coverage..."
	@if ! docker compose -f infra/docker-compose.yml -p receipt ps app | grep -q "Up"; then \
		echo "🚀 Démarrage du container pour les tests..."; \
		$(DC) up -d; \
		sleep 3; \
	fi
	@echo "🔧 Installing PCOV extension if needed..."
	@$(DC) exec app sh -c "pecl list | grep -q pcov || pecl install pcov || true"
	@$(DC) exec app sh -c "php -m | grep -q pcov || echo 'extension=pcov.so' > /usr/local/etc/php/conf.d/pcov.ini || true"
	@$(DC) exec app sh -c "cd /var/www/html && php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit -c /phpunit.xml --coverage-text"

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

deploy-direct:
	@./scripts/deploy-direct.sh

install-hooks:
	@./scripts/install-git-hooks.sh


# --- Assets ---
build-assets:
	@echo "📦 Build des assets avec cache-busting..."
	@./scripts/build-assets.sh

cache-bust:
	@echo "🔄 Cache-busting automatique..."
	@./scripts/cache-bust-safe.sh

# Préparation CDN (cache-busting par hash MD5)
prepare-cdn:
	@echo "🌐 Préparation des assets pour CDN..."
	@chmod +x scripts/prepare-cdn-simple.sh
	@./scripts/prepare-cdn-simple.sh

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
