# GuildForge - Makefile

.PHONY: help up down build restart recreate logs ps shell shell-node shell-db \
        es-test es-logs es-health es-indices \
        install setup fresh migrate migrate-rollback seed db-backup db-restore \
        test test-unit test-feature test-coverage test-filter test-serial \
        check check-fix cs cs-fix phpstan lint-js lint-js-fix types \
        check-q test-q lint format db-reset \
        dev build-assets cache cache-clear routes tinker ide-helper \
        make-model make-migration make-controller make-resource \
        make-request make-test make-filament hooks pre-commit \
        prod-build prod-build-clean prod-up prod-down prod-logs prod-shell prod-fresh prod-ps

# Default target
.DEFAULT_GOAL := help

# Docker compose command
DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_PROD := docker compose -f docker-compose.prod.yml
PHP_CONTAINER := guildforge_app
NODE_CONTAINER := guildforge_node
DB_CONTAINER := guildforge_db
PHP_CONTAINER_PROD := guildforge_app_prod
DB_CONTAINER_PROD := guildforge_db_prod

# =============================================================================
# HELP
# =============================================================================

help: ## Show this help message
	@echo "GuildForge"
	@echo ""
	@echo "Usage: make [target]"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

# =============================================================================
# DOCKER
# =============================================================================

up: ## Start all containers
	@echo "Starting containers..."
	$(DOCKER_COMPOSE) up -d
	@echo "Containers started!"
	@echo "Application: http://localhost:8080"
	@echo "Mailpit: http://localhost:8025"
	@echo "Vite: http://localhost:5173"
	@echo "Elasticsearch: http://localhost:9200"
	@echo "Kibana: http://localhost:5601"

down: ## Stop all containers
	@echo "Stopping containers..."
	$(DOCKER_COMPOSE) down

build: ## Rebuild containers
	@echo "Rebuilding containers..."
	$(DOCKER_COMPOSE) build --no-cache

restart: ## Restart all containers
	$(DOCKER_COMPOSE) restart

recreate: ## Recreate all containers (pulls new images)
	$(DOCKER_COMPOSE) up -d --force-recreate

logs: ## Tail all logs
	$(DOCKER_COMPOSE) logs -f

ps: ## Show container status
	$(DOCKER_COMPOSE) ps

# =============================================================================
# SHELL ACCESS
# =============================================================================

shell: ## Enter PHP container
	docker exec -it $(PHP_CONTAINER) sh

shell-node: ## Enter Node container
	docker exec -it $(NODE_CONTAINER) sh

shell-db: ## Enter PostgreSQL shell
	docker exec -it $(DB_CONTAINER) psql -U $${DB_USERNAME:-guildforge} -d $${DB_DATABASE:-guildforge}

# =============================================================================
# INSTALLATION
# =============================================================================

install: ## Install all dependencies
	@echo "Installing PHP dependencies..."
	docker exec $(PHP_CONTAINER) composer install
	@echo "Installing Node dependencies..."
	docker exec $(NODE_CONTAINER) npm install
	@echo "All dependencies installed!"

setup: install ## Full setup (install + migrate + seed)
	@echo "Running setup..."
	docker exec $(PHP_CONTAINER) sh -c "cp -n .env.example .env 2>/dev/null || true"
	docker exec $(PHP_CONTAINER) php artisan key:generate --ansi
	docker exec $(PHP_CONTAINER) php artisan migrate --seed
	docker exec $(PHP_CONTAINER) php artisan storage:link
	@echo "Setup complete!"

fresh: ## Fresh migration with seeders
	@echo "Running fresh migrations..."
	docker exec $(PHP_CONTAINER) php artisan migrate:fresh --seed

# =============================================================================
# DATABASE
# =============================================================================

migrate: ## Run migrations
	docker exec $(PHP_CONTAINER) php artisan migrate

migrate-rollback: ## Rollback last migration
	docker exec $(PHP_CONTAINER) php artisan migrate:rollback

seed: ## Run seeders
	docker exec $(PHP_CONTAINER) php artisan db:seed

db-backup: ## Backup database to ./backups/
	@mkdir -p backups
	docker exec $(DB_CONTAINER) pg_dump -U $${DB_USERNAME:-guildforge} $${DB_DATABASE:-guildforge} > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created in ./backups/"

db-restore: ## Restore from latest backup
	@LATEST=$$(ls -t backups/*.sql 2>/dev/null | head -1); \
	if [ -z "$$LATEST" ]; then \
		echo "No backup files found in ./backups/"; \
		exit 1; \
	fi; \
	echo "Restoring from $$LATEST"; \
	cat $$LATEST | docker exec -i $(DB_CONTAINER) psql -U $${DB_USERNAME:-guildforge} -d $${DB_DATABASE:-guildforge}

# =============================================================================
# TESTING (all tests run in parallel with 8 processes)
# =============================================================================

test: ## Run all tests (parallel)
	docker exec $(PHP_CONTAINER) php artisan test --parallel --processes=8

test-unit: ## Run unit tests only (parallel)
	docker exec $(PHP_CONTAINER) php artisan test --testsuite=Unit --parallel --processes=8

test-feature: ## Run feature tests only (parallel)
	docker exec $(PHP_CONTAINER) php artisan test --testsuite=Feature --parallel --processes=8

test-coverage: ## Run tests with coverage report (parallel)
	docker exec $(PHP_CONTAINER) php artisan test --parallel --processes=8 --coverage --coverage-html=coverage

test-filter: ## Run specific test (usage: make test-filter FILTER=EventTest)
	docker exec $(PHP_CONTAINER) php artisan test --filter=$(FILTER)

test-serial: ## Run all tests without parallelization
	docker exec $(PHP_CONTAINER) php artisan test

# =============================================================================
# CODE QUALITY
# =============================================================================

check: cs phpstan lint-js types ## Run ALL checks
	@echo "All checks passed!"

check-fix: cs-fix lint-js-fix ## Fix all auto-fixable issues
	@echo "All auto-fixable issues resolved!"

cs: ## PHP CS Fixer (dry-run)
	docker exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix PHP code style
	docker exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix

phpstan: ## Static analysis
	docker exec $(PHP_CONTAINER) vendor/bin/phpstan analyse

lint-js: ## ESLint
	docker exec $(NODE_CONTAINER) npm run lint

lint-js-fix: ## Fix ESLint issues
	docker exec $(NODE_CONTAINER) npm run lint:fix

types: ## TypeScript type check
	docker exec $(NODE_CONTAINER) npm run type-check

# =============================================================================
# QUIET VERSIONS (for CI - minimal output)
# =============================================================================

check-q: ## Check with minimal output
	@docker exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix --dry-run --quiet && echo "✓ CS" || echo "✗ CS"
	@docker exec $(PHP_CONTAINER) vendor/bin/phpstan analyse --no-progress -q && echo "✓ PHPStan" || echo "✗ PHPStan"
	@docker exec $(NODE_CONTAINER) npm run lint --silent && echo "✓ ESLint" || echo "✗ ESLint"
	@docker exec $(NODE_CONTAINER) npm run type-check --silent && echo "✓ Types" || echo "✗ Types"

test-q: ## Tests with compact output (parallel)
	@docker exec $(PHP_CONTAINER) php artisan test --parallel --processes=8 --compact

lint: check ## Alias for check

format: ## Format all (CS Fixer + ESLint + Prettier)
	@docker exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix --quiet
	@docker exec $(NODE_CONTAINER) npm run lint:fix --silent
	@docker exec $(NODE_CONTAINER) npx prettier --write "resources/js/**/*.{ts,vue}" --log-level error

db-reset: ## Reset database completely
	@docker exec $(PHP_CONTAINER) php artisan db:wipe --force && docker exec $(PHP_CONTAINER) php artisan migrate --seed

# =============================================================================
# FRONTEND
# =============================================================================

dev: ## Vite dev server with HMR
	docker exec $(NODE_CONTAINER) npm run dev

build-assets: ## Production build
	docker exec $(NODE_CONTAINER) npm run build

# =============================================================================
# CODE GENERATION
# =============================================================================

make-model: ## Create Model + migration + factory (usage: make make-model NAME=Event)
	docker exec $(PHP_CONTAINER) php artisan make:model $(NAME) -mf

make-migration: ## Create migration (usage: make make-migration NAME=create_events_table)
	docker exec $(PHP_CONTAINER) php artisan make:migration $(NAME)

make-controller: ## Create controller (usage: make make-controller NAME=EventController)
	docker exec $(PHP_CONTAINER) php artisan make:controller $(NAME)

make-resource: ## Create API resource (usage: make make-resource NAME=EventResource)
	docker exec $(PHP_CONTAINER) php artisan make:resource $(NAME)

make-request: ## Create form request (usage: make make-request NAME=StoreEventRequest)
	docker exec $(PHP_CONTAINER) php artisan make:request $(NAME)

make-test: ## Create test (usage: make make-test NAME=EventTest)
	docker exec $(PHP_CONTAINER) php artisan make:test $(NAME)

make-filament: ## Create Filament resource (usage: make make-filament NAME=Event)
	docker exec $(PHP_CONTAINER) php artisan make:filament-resource $(NAME) --generate

# =============================================================================
# LARAVEL UTILITIES
# =============================================================================

cache: ## Create all caches
	docker exec $(PHP_CONTAINER) php artisan config:cache
	docker exec $(PHP_CONTAINER) php artisan route:cache
	docker exec $(PHP_CONTAINER) php artisan view:cache

cache-clear: ## Clear all caches
	docker exec $(PHP_CONTAINER) php artisan config:clear
	docker exec $(PHP_CONTAINER) php artisan route:clear
	docker exec $(PHP_CONTAINER) php artisan view:clear
	docker exec $(PHP_CONTAINER) php artisan cache:clear

routes: ## List all routes
	docker exec $(PHP_CONTAINER) php artisan route:list

tinker: ## Open Tinker
	docker exec -it $(PHP_CONTAINER) php artisan tinker

ide-helper: ## Generate IDE helpers
	docker exec $(PHP_CONTAINER) php artisan ide-helper:generate
	docker exec $(PHP_CONTAINER) php artisan ide-helper:models --nowrite
	docker exec $(PHP_CONTAINER) php artisan ide-helper:meta

# =============================================================================
# ELASTICSEARCH
# =============================================================================

es-test: ## Test Elasticsearch connection and send test logs
	docker exec $(PHP_CONTAINER) php artisan elasticsearch:test

es-logs: ## View recent Elasticsearch container logs
	docker logs guildforge_elasticsearch --tail 50

es-health: ## Check Elasticsearch cluster health
	@curl -s http://localhost:9200/_cluster/health?pretty

es-indices: ## List all Elasticsearch indices
	@curl -s http://localhost:9200/_cat/indices?v

# =============================================================================
# GIT HOOKS
# =============================================================================

hooks: ## Install pre-commit hook
	@mkdir -p .git/hooks
	@printf '#!/bin/sh\nmake pre-commit' > .git/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit
	@echo "Git hooks installed!"

pre-commit: cs phpstan types ## Run pre-commit checks
	@echo "Pre-commit checks passed!"

# =============================================================================
# PRODUCTION LOCAL (prod-local simulation)
# =============================================================================

prod-build: ## Build production image (with cache)
	@echo "Building production image..."
	DOCKER_BUILDKIT=1 $(DOCKER_COMPOSE_PROD) build
	@echo "Production image built!"

prod-build-clean: ## Build production image (no cache)
	@echo "Building production image from scratch..."
	DOCKER_BUILDKIT=1 $(DOCKER_COMPOSE_PROD) build --no-cache
	@echo "Production image built!"

prod-up: ## Start prod-local environment
	@echo "Starting prod-local environment..."
	$(DOCKER_COMPOSE_PROD) up -d
	@echo "Prod-local started!"
	@echo "Application: http://localhost:8000"

prod-down: ## Stop prod-local environment
	@echo "Stopping prod-local environment..."
	$(DOCKER_COMPOSE_PROD) down

prod-logs: ## Tail prod-local logs
	$(DOCKER_COMPOSE_PROD) logs -f

prod-shell: ## Enter prod-local container
	docker exec -it $(PHP_CONTAINER_PROD) sh

prod-fresh: ## Fresh migration in prod-local
	@echo "Running fresh migrations in prod-local..."
	docker exec $(PHP_CONTAINER_PROD) php artisan migrate:fresh --seed --force

prod-ps: ## Show prod-local container status
	$(DOCKER_COMPOSE_PROD) ps
