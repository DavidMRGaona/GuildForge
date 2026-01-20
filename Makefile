# Association website - Makefile

.PHONY: help up down build restart logs ps shell shell-node shell-db \
        install setup fresh migrate migrate-rollback seed db-backup db-restore \
        test test-unit test-feature test-coverage test-filter test-parallel \
        check check-fix cs cs-fix phpstan lint-js lint-js-fix types \
        check-q test-q lint format db-reset \
        dev build-assets cache cache-clear routes tinker ide-helper \
        make-model make-migration make-controller make-resource \
        make-request make-test make-filament hooks pre-commit

# Default target
.DEFAULT_GOAL := help

# Docker compose command
DOCKER_COMPOSE := docker compose
PHP_CONTAINER := association_app
NODE_CONTAINER := association_node
DB_CONTAINER := association_db

# =============================================================================
# HELP
# =============================================================================

help: ## Show this help message
	@echo "Association website"
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

down: ## Stop all containers
	@echo "Stopping containers..."
	$(DOCKER_COMPOSE) down

build: ## Rebuild containers
	@echo "Rebuilding containers..."
	$(DOCKER_COMPOSE) build --no-cache

restart: ## Restart all containers
	$(DOCKER_COMPOSE) restart

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
	docker exec -it $(DB_CONTAINER) psql -U $${DB_USERNAME:-association} -d $${DB_DATABASE:-association}

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
	docker exec $(DB_CONTAINER) pg_dump -U $${DB_USERNAME:-association} $${DB_DATABASE:-association} > backups/backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backup created in ./backups/"

db-restore: ## Restore from latest backup
	@LATEST=$$(ls -t backups/*.sql 2>/dev/null | head -1); \
	if [ -z "$$LATEST" ]; then \
		echo "No backup files found in ./backups/"; \
		exit 1; \
	fi; \
	echo "Restoring from $$LATEST"; \
	cat $$LATEST | docker exec -i $(DB_CONTAINER) psql -U $${DB_USERNAME:-association} -d $${DB_DATABASE:-association}

# =============================================================================
# TESTING
# =============================================================================

test: ## Run all tests
	docker exec $(PHP_CONTAINER) php artisan test

test-unit: ## Run unit tests only
	docker exec $(PHP_CONTAINER) php artisan test --testsuite=Unit

test-feature: ## Run feature tests only
	docker exec $(PHP_CONTAINER) php artisan test --testsuite=Feature

test-coverage: ## Run tests with coverage report
	docker exec $(PHP_CONTAINER) php artisan test --coverage --coverage-html=coverage

test-filter: ## Run specific test (usage: make test-filter FILTER=EventTest)
	docker exec $(PHP_CONTAINER) php artisan test --filter=$(FILTER)

test-parallel: ## Run tests in parallel
	docker exec $(PHP_CONTAINER) php artisan test --parallel

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

test-q: ## Tests with compact output
	@docker exec $(PHP_CONTAINER) php artisan test --compact

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
# GIT HOOKS
# =============================================================================

hooks: ## Install pre-commit hook
	@mkdir -p .git/hooks
	@printf '#!/bin/sh\nmake pre-commit' > .git/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit
	@echo "Git hooks installed!"

pre-commit: cs phpstan types ## Run pre-commit checks
	@echo "Pre-commit checks passed!"
