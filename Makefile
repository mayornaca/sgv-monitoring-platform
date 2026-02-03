.PHONY: help up down restart build logs shell mysql postgres cache migrate test clean

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
RESET := \033[0m

help: ## Show this help message
	@echo "$(BLUE)SGV Docker - Available Commands:$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-15s$(RESET) %s\n", $$1, $$2}'

up: ## Start all services
	docker-compose up -d
	@echo "$(GREEN)Services started. Access: http://localhost$(RESET)"

down: ## Stop all services
	docker-compose down

restart: ## Restart all services
	docker-compose restart

build: ## Build/rebuild Docker images
	docker-compose build

logs: ## Show logs from all services
	docker-compose logs -f

shell: ## Access PHP container shell
	docker-compose exec app bash

mysql: ## Access MySQL shell
	docker-compose exec mysql mysql -u sgv_user -psgv_password gesvial_sgv

postgres: ## Access PostgreSQL shell
	docker-compose exec postgresql psql -U postgres -d dbpuente

cache: ## Clear Symfony cache
	docker-compose exec app php bin/console cache:clear

migrate: ## Run database migrations (MySQL and PostgreSQL)
	@echo "$(BLUE)Running MySQL migrations...$(RESET)"
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(BLUE)Running PostgreSQL migrations...$(RESET)"
	docker-compose exec app php bin/console doctrine:migrations:migrate --em=siv --no-interaction
	@echo "$(GREEN)Migrations completed$(RESET)"

composer-install: ## Install Composer dependencies
	docker-compose exec app composer install

composer-update: ## Update Composer dependencies
	docker-compose exec app composer update

test: ## Run tests (if configured)
	docker-compose exec app php bin/phpunit

clean: ## Remove all containers, volumes, and images
	docker-compose down -v --rmi all
	@echo "$(GREEN)Docker environment cleaned$(RESET)"

status: ## Show status of all services
	docker-compose ps

tools: ## Start with management tools (phpMyAdmin, pgAdmin)
	docker-compose --profile tools up -d
	@echo "$(GREEN)Services with tools started$(RESET)"
	@echo "phpMyAdmin: http://localhost:8080"
	@echo "pgAdmin: http://localhost:8081"
