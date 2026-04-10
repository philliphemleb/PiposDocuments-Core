# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh bash composer vendor sf cc migrate test setup analyse lint lint-fix

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

bash: ## Connect to the FrankenPHP container via bash
	@$(PHP_CONT) bash

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

## —— Database 🗄️ ——————————————————————————————————————————————————————————————
migrate: ## Run Doctrine migrations
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing

setup: ## Create the test database and run migrations against it (run once after first make up)
	@$(SYMFONY) doctrine:database:create --env=test --if-not-exists
	@$(SYMFONY) doctrine:migrations:migrate --env=test --no-interaction --all-or-nothing

## —— Tests 🧪 —————————————————————————————————————————————————————————————————
test: ## Run the test suite, pass the parameter "c=" to add phpunit options, example: make test c="--group e2e"
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)

## —— Quality 🔎 ———————————————————————————————————————————————————————————————
analyse: ## Run PHPStan static analysis (level max)
	@$(COMPOSER) analyse

lint: ## Check code style with PHP-CS-Fixer (dry run)
	@$(COMPOSER) lint

lint-fix: ## Auto-fix code style with PHP-CS-Fixer
	@$(COMPOSER) lint:fix
