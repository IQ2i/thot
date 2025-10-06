# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT  = $(DOCKER_COMP) run --rm -w /app php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer

.DEFAULT_GOAL= help

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "Usage: make \033[36m<target>\033[0m\n"} /^[a-z\/\.A-Z0-9_-]+:.*?##/ { printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

## —— Docker —————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

php: ## Connect to the PHP container
	$(PHP_CONT) bash

## —— App ————————————————————————————————————————————————————————————————————
install:
	$(COMPOSER) install
	$(PHP) bin/console doctrine:schema:update --force
	$(PHP) bin/console doctrine:fixtures:load --no-interaction
	$(PHP) bin/console tailwind:build

fixtures:
	$(PHP) bin/console doctrine:fixtures:load --no-interaction

## —— CI —————————————————————————————————————————————————————————————————————
ci: static test

static: ## Run static analysis tools
	$(COMPOSER) static:run

test: ## Run tests
	$(COMPOSER) test:run

## —— Assets —————————————————————————————————————————————————————————————————
watch:
	$(PHP) bin/console tailwind:build --watch
