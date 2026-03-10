.PHONY: help up down build install init migrate fixtures reset logs shell test fix-perms

# Detect host user/group for permission fixes
HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)

# Default target
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

up: ## Start all containers
	docker-compose up -d

down: ## Stop all containers
	docker-compose down

build: ## Build Docker images
	docker-compose build --no-cache

install: ## Install Composer dependencies
	docker-compose run --rm --user root php composer install

init: build up install migrate fixtures ## Full first-run setup
	@echo ""
	@echo "✅ Setup complete!"
	@echo "  → App:     http://crypto.localhost:8001"
	@echo "             http://finance.localhost:8001"
	@echo "             http://gambling.localhost:8001"
	@echo "  → Adminer: http://localhost:8080  (Server: mysql)"
	@echo "  → n8n:     http://localhost:5678  (admin/admin)"
	@echo ""
	@echo "MySQL port: 3307 (host) → 3306 (container)"

migrate: ## Run database migrations
	docker-compose run --rm --user root php php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Load fixtures (dev only)
	docker-compose run --rm --user root php php bin/console doctrine:fixtures:load --no-interaction

reset: ## Reset database (drop, create, migrate, fixtures)
	docker-compose run --rm --user root php php bin/console doctrine:database:drop --force --if-exists
	docker-compose run --rm --user root php php bin/console doctrine:database:create
	$(MAKE) migrate
	$(MAKE) fixtures

logs: ## Show PHP container logs
	docker-compose logs -f php

shell: ## Open shell in PHP container
	docker-compose exec --user root php sh

cache-clear: ## Clear Symfony cache
	docker-compose run --rm --user root php php bin/console cache:clear

test: ## Run PHPUnit tests
	docker-compose run --rm --user root php php bin/phpunit

composer-require: ## Add a package: make composer-require pkg=vendor/package
	docker-compose run --rm --user root php composer require $(pkg)

cc: cache-clear ## Alias for cache-clear

mysql: ## Open MySQL console (database: multisite)
	docker-compose exec mysql mysql -u app -psecret multisite

redis: ## Open Redis CLI
	docker-compose exec redis redis-cli

fix-perms: ## Fix file permissions so PhpStorm/host user can edit files
	@echo "Setting ownership to $(HOST_UID):$(HOST_GID) on all project files..."
	docker-compose run --rm --user root php chown -R $(HOST_UID):$(HOST_GID) /var/www
	@echo "✅ Done. Files are now editable by your host user."
