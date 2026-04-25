.PHONY: help docker-up docker-down docker-build \
        test test-mysql test-pgsql test-sqlsrv test-unit \
        test-docker test-docker-mysql test-docker-pgsql test-docker-sqlsrv test-docker-unit \
        sh coverage phpstan cs-fix cs-check ci

# ----- Docker compose helpers -----
DC                  = docker compose
DOCKER_PHP          = $(DC) run --rm php

# DATABASE_URLs used inside the docker network (service hostnames, internal ports)
URL_MYSQL           = mysql://test_user:test_password@db_mysql:3306/test_db?serverVersion=8.0
URL_PGSQL           = postgresql://test_user:test_password@db_postgres:5432/test_db?serverVersion=15
URL_SQLSRV          = pdo-sqlsrv://sa:StrongPassw0rd@db_mssql:1433/test_db?serverVersion=2022&charset=UTF-8

DOCKER_PHP_MYSQL    = $(DC) run --rm -e DATABASE_URL='$(URL_MYSQL)' php
DOCKER_PHP_PGSQL    = $(DC) run --rm -e DATABASE_URL='$(URL_PGSQL)' php
DOCKER_PHP_SQLSRV   = $(DC) run --rm -e DATABASE_URL='$(URL_SQLSRV)' php

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}'

# ----- Container lifecycle -----
docker-up: ## Start all containers (MySQL, PostgreSQL, SQL Server, php)
	$(DC) up -d

docker-down: ## Stop all containers
	$(DC) down

docker-build: ## (Re)build the PHP container image
	$(DC) build php

sh: ## Open an interactive shell inside the php container
	$(DOCKER_PHP) bash

# ----- Tests using LOCAL php (fast, requires the right extensions installed) -----
# Auto-detect whether pdo_sqlsrv is loaded so SQL Server tests are only included
# when the extension is actually available — avoids "could not find driver" noise
# for contributors who haven't installed msodbcsql + pdo_sqlsrv locally.
HAS_SQLSRV := $(shell php -r "echo extension_loaded('pdo_sqlsrv') ? 1 : 0;" 2>/dev/null)

ifeq ($(HAS_SQLSRV),1)
test: test-unit test-mysql test-pgsql test-sqlsrv ## Run unit + MySQL + PostgreSQL + SQL Server tests with local PHP
else
test: test-unit test-mysql test-pgsql ## Run unit + MySQL + PostgreSQL tests with local PHP (SQL Server skipped — install pdo_sqlsrv or use `make test-docker`)
	@echo ""
	@echo "ℹ️  SQL Server tests skipped — pdo_sqlsrv is not loaded in your local PHP."
	@echo "   Use \`make test-docker\` to run the full suite, or install pdo_sqlsrv (see CONTRIBUTING.md)."
endif

test-mysql: ## Run MySQL functional tests (local PHP)
	vendor/bin/phpunit --testsuite=mysql

test-pgsql: ## Run PostgreSQL functional tests (local PHP)
	vendor/bin/phpunit -c phpunit-postgresql.xml.dist

test-sqlsrv: ## Run SQL Server functional tests (local PHP — needs pdo_sqlsrv)
	vendor/bin/phpunit -c phpunit-sqlserver.xml.dist

test-unit: ## Run unit tests (local PHP)
	vendor/bin/phpunit --testsuite=unit

# ----- Tests using the dockerized php (zero local config required) -----
test-docker: test-docker-unit test-docker-mysql test-docker-pgsql test-docker-sqlsrv ## Run all tests inside the php container

test-docker-mysql: ## Run MySQL tests inside the php container
	$(DOCKER_PHP_MYSQL) vendor/bin/phpunit --testsuite=mysql

test-docker-pgsql: ## Run PostgreSQL tests inside the php container
	$(DOCKER_PHP_PGSQL) vendor/bin/phpunit -c phpunit-postgresql.xml.dist

test-docker-sqlsrv: ## Run SQL Server tests inside the php container
	$(DOCKER_PHP_SQLSRV) vendor/bin/phpunit -c phpunit-sqlserver.xml.dist

test-docker-unit: ## Run unit tests inside the php container
	$(DOCKER_PHP) vendor/bin/phpunit --testsuite=unit

# ----- Quality -----
coverage: ## Generate clover.xml coverage from unit tests inside the docker container
	$(DC) run --rm -e XDEBUG_MODE=coverage php vendor/bin/phpunit --testsuite=unit \
		--coverage-clover=coverage/clover.xml --coverage-text

coverage-full: ## Generate full coverage by running unit + all 3 platforms (slow)
	$(DC) run --rm -e XDEBUG_MODE=coverage -e DATABASE_URL='$(URL_MYSQL)' php \
		vendor/bin/phpunit --testsuite=unit --coverage-clover=coverage/clover-unit.xml
	$(DC) run --rm -e XDEBUG_MODE=coverage -e DATABASE_URL='$(URL_MYSQL)' php \
		vendor/bin/phpunit --testsuite=mysql --coverage-clover=coverage/clover-mysql.xml --coverage-text

phpstan: ## Run PHPStan static analysis
	vendor/bin/phpstan analyse src

cs-fix: ## Fix code style with PHP-CS-Fixer
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src

cs-check: ## Check code style (dry-run)
	PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix src --dry-run --diff

ci: cs-check phpstan test ## Full CI pipeline locally
