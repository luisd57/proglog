COMPOSE = docker-compose

.PHONY: help build up down restart logs shell composer sf db-create db-migrate db-diff cache-clear seed test test-unit test-integration test-db-create test-db-migrate test-db-setup

help:
	@echo "Available commands:"
	@echo "  make build            - Build Docker containers"
	@echo "  make up               - Start Docker containers"
	@echo "  make down             - Stop Docker containers"
	@echo "  make restart          - Restart Docker containers"
	@echo "  make logs             - View Docker logs"
	@echo "  make shell            - Access PHP container shell"
	@echo "  make composer         - Run Composer command (use: make composer c='require package')"
	@echo "  make sf               - Run Symfony console command (use: make sf c='cache:clear')"
	@echo "  make db-create        - Create database"
	@echo "  make db-migrate       - Run database migrations"
	@echo "  make db-diff          - Generate migration from entities"
	@echo "  make cache-clear      - Clear Symfony cache"
	@echo "  make seed             - Seed built-in exercises"
	@echo "  make test             - Run PHPUnit tests"
	@echo "  make test-unit        - Run Unit suite (no DB)"
	@echo "  make test-integration - Run Integration suite"
	@echo "  make test-db-setup    - Create + migrate the test database"

# Docker commands
build:
	$(COMPOSE) build

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs -f

shell:
	$(COMPOSE) exec php sh

# Composer command
composer:
	$(COMPOSE) exec php composer $(c)

# Symfony console command
sf:
	$(COMPOSE) exec php php bin/console $(c)

# Database commands
db-create:
	$(COMPOSE) exec php php bin/console doctrine:database:create --if-not-exists

db-migrate:
	$(COMPOSE) exec php php bin/console doctrine:migrations:migrate --no-interaction

db-diff:
	$(COMPOSE) exec php php bin/console doctrine:migrations:diff

# Cache
cache-clear:
	$(COMPOSE) exec php php bin/console cache:clear

# Seed data
seed:
	$(COMPOSE) exec php php bin/console app:seed-exercises

# Tests
test:
	$(COMPOSE) exec php vendor/bin/phpunit

test-unit:
	$(COMPOSE) exec php vendor/bin/phpunit --testsuite=Unit

test-integration:
	$(COMPOSE) exec php vendor/bin/phpunit --testsuite=Integration

test-db-create:
	$(COMPOSE) exec php php bin/console doctrine:database:create --env=test --if-not-exists

test-db-migrate:
	$(COMPOSE) exec php php bin/console doctrine:migrations:migrate --env=test --no-interaction

test-db-setup: test-db-create test-db-migrate
