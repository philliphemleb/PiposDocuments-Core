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
.PHONY        : help build up start down logs sh bash composer vendor sf cc migrate migrate-fresh test setup analyse lint lint-fix rector rector-fix hooks

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: hooks ## Start the docker hub in detached mode (no logs)
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
migrate: ## Run Doctrine migrations (ENV=all for both dev and test)
	@if [ "$(ENV)" = "all" ]; then \
		echo ">> Migrating dev database"; \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing --env=dev; \
		echo ">> Migrating test database"; \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing --env=test; \
	elif [ -z "$(ENV)" ]; then \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing; \
	else \
		echo ">> Migrating $(ENV) database"; \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing --env=$(ENV); \
	fi

setup: ## Setups database and run migrations against it (run once after first make up)
	@if [ -z "$(ENV)" ]; then \
    	echo ">> Setups test database"; \
    	if [ "$(DROP)" = "true" ]; then \
    	    $(SYMFONY) doctrine:database:drop --force --env=test --if-exists; \
		fi; \
    	$(SYMFONY) doctrine:database:create --env=test --if-not-exists; \
    	$(SYMFONY) doctrine:migrations:migrate 0 --no-interaction --allow-no-migration --env=test; \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing --env=test; \
	elif [ "$(ENV)" = "prod" ]; then \
	    echo ">> Can not run setup for prod"; \
	else \
		echo ">> Setups $(ENV) database"; \
		if [ "$(DROP)" = "true" ]; then \
			$(SYMFONY) doctrine:database:drop --force --env=$(ENV) --if-exists; \
		fi; \
		$(SYMFONY) doctrine:database:create --env=$(ENV) --if-not-exists; \
		$(SYMFONY) doctrine:migrations:migrate 0 --no-interaction --allow-no-migration --env=$(ENV); \
		$(SYMFONY) doctrine:migrations:migrate --no-interaction --all-or-nothing --env=$(ENV); \
	fi

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

rector: ## Run Rector in dry-run mode (CI-mode — fails if any change would be made)
	@$(COMPOSER) rector

rector-fix: ## Apply Rector refactorings to the codebase
	@$(COMPOSER) rector:fix

## —— Git Hooks 🪝 ————————————————————————————————————————————————————————————
hooks: ## Install git hooks from .githooks/
	@git config core.hooksPath .githooks
	@echo "Git hooks installed (.githooks/)"
