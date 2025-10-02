# ====== Config ======
COMPOSE ?= docker compose
FILE    ?= docker-compose.yml
PROJ    ?= receipt

# Raccourci
DC = $(COMPOSE) -f $(FILE) -p $(PROJ)

# ====== Cibles ======
.PHONY: help up down restart ps logs install sh-api

help:
	@echo "Cibles :"
	@echo "  make up        -> démarrer les containers en arrière-plan"
	@echo "  make down      -> arrêter et supprimer les containers/réseaux"
	@echo "  make restart   -> redémarrer la stack"
	@echo "  make ps        -> état des services"
	@echo "  make logs      -> logs suivis (api + web)"
	@echo "  make install   -> composer install dans le service 'api'"
	@echo "  make sh-api    -> shell dans le conteneur 'api'"

# --- Docker Compose ---
up:
	$(DC) up -d

down:
	$(DC) down

restart: down up

ps:
	$(DC) ps

logs:
	$(DC) logs -f api web

# --- Composer (dans le service 'api') ---
# On utilise 'run' pour ne pas exiger que le conteneur soit déjà démarré.
install:
	$(DC) run --rm --no-deps api \
		composer install --no-interaction --prefer-dist --optimize-autoloader

# Shell dans le conteneur API (essaie bash puis sh)
sh-api:
	-$(DC) exec api bash -lc 'cd /var/www/html && exec bash' || \
	$(DC) exec api sh -lc 'cd /var/www/html && exec sh'
