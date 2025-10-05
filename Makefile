# ====== Config ======
COMPOSE ?= docker compose
FILE    ?= infra/docker-compose.yml
PROJ    ?= receipt

# Raccourci
DC = $(COMPOSE) -f $(FILE) -p $(PROJ)

# ====== Cibles ======
.PHONY: help up down restart ps logs install sh-app lint check-quality format

help:
	@echo "Cibles :"
	@echo "  make up            -> démarrer les containers en arrière-plan"
	@echo "  make down          -> arrêter et supprimer les containers/réseaux"
	@echo "  make restart       -> redémarrer la stack"
	@echo "  make ps            -> état des services"
	@echo "  make logs          -> logs suivis (app)"
	@echo "  make install       -> composer install dans le service 'app'"
	@echo "  make sh-app        -> shell dans le conteneur 'app'"
	@echo ""
	@echo "Qualité de code :"
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
