# Contributing

Thanks for your interest! Please make sure tests pass on the platforms your change is meant to support before opening a PR.

## Quick start (recommended)

The repo ships with a fully containerized PHP environment that already has every required extension (including `pdo_sqlsrv`). **You don't need to install anything other than Docker.**

```bash
make docker-up        # spawns MySQL, PostgreSQL, SQL Server and a php container
make test-docker      # runs the full test suite (unit + 3 platforms) inside the php container
```

That's it. No PHP, no PECL, no Microsoft ODBC drivers to install on your machine.

Other useful targets:

```bash
make test-docker-mysql     # MySQL only, in container
make test-docker-pgsql     # PostgreSQL only, in container
make test-docker-sqlsrv    # SQL Server only, in container
make test-docker-unit      # unit tests, in container
make sh                    # interactive shell inside the php container
```

## Running tests with your local PHP

If you have a local PHP install with the right extensions, you can skip Docker for faster runs:

```bash
make test                  # all platforms with local PHP
make test-mysql            # MySQL only
make test-pgsql            # PostgreSQL only
make test-sqlsrv           # SQL Server only (requires pdo_sqlsrv, see below)
make test-unit             # unit tests
```

You can override `DATABASE_URL` on the fly:

```bash
DATABASE_URL='postgresql://test_user:test_password@127.0.0.1:5433/test_db?serverVersion=15' \
    vendor/bin/phpunit --testsuite=postgresql
```

### Installing pdo_sqlsrv locally (only if you bypass Docker)

#### macOS (Homebrew PHP)

```bash
brew tap microsoft/mssql-release https://github.com/Microsoft/homebrew-mssql-release
brew install msodbcsql18 mssql-tools18

pecl install sqlsrv
pecl install pdo_sqlsrv

php -m | grep -i sqlsrv          # should print sqlsrv and pdo_sqlsrv
```

If `php -m` doesn't list them, add to your `php.ini` (path via `php --ini`):

```ini
extension=sqlsrv.so
extension=pdo_sqlsrv.so
```

#### Ubuntu / Debian

```bash
curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/$(lsb_release -rs)/prod.list | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev

sudo pecl install sqlsrv pdo_sqlsrv
# then add the two extension= lines to your php.ini
```

## Quality checks

```bash
make phpstan         # static analysis (level 8)
make cs-check        # code style check (dry-run)
make cs-fix          # code style fix
make ci              # full pipeline locally: cs-check + phpstan + tests
```

## How tests are organised

| Directory | What it tests |
|---|---|
| `tests/Unit/` | Platform-agnostic unit tests (no Symfony Kernel, no DB) — fast |
| `tests/Functional/Mysql/` | End-to-end tests against MySQL/MariaDB |
| `tests/Functional/Postgresql/` | End-to-end tests against PostgreSQL |
| `tests/Functional/SqlServer/` | End-to-end tests against SQL Server |

Each platform has its own PHPUnit configuration (`phpunit.xml.dist`, `phpunit-postgresql.xml.dist`, `phpunit-sqlserver.xml.dist`) so you don't have to edit anything to switch.

## CI

Every push and PR runs the full matrix on GitHub Actions: PHP 8.2 / 8.3 / 8.4 × Symfony 6.4 / 7.4 / 8.0 × MySQL / PostgreSQL / SQL Server. See `.github/workflows/ci.yml`.
