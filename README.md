# 🧩 Trigger Mapping Bundle

[![Packagist Version](https://img.shields.io/packagist/v/talleu/trigger-mapping.svg)](https://packagist.org/packages/talleu/trigger-mapping)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![CI](https://github.com/clementtalleu/trigger-mapping/actions/workflows/ci.yml/badge.svg)](https://github.com/clementtalleu/trigger-mapping/actions)

> **Map, validate, version-control and deploy your database [SQL triggers](https://sql.sh/cours/create-trigger) declaratively from your Doctrine entities — with PHP attributes.**

```php
namespace App\Entity;

use Talleu\TriggerMapping\Attribute\Trigger;
use App\Triggers\UpdateUserTimestamp;

#[ORM\Entity]
#[Trigger(
    name: 'trg_user_updated_at',
    function: 'fn_update_timestamp',
    on: ['INSERT', 'UPDATE'],
    when: 'BEFORE',
    scope: 'ROW',
    className: UpdateUserTimestamp::class,
)]
class User { /* ... */ }
```

Your trigger schema becomes **declarative, reviewable in PRs, version-controlled alongside your code, and validated by your CI**. No more SQL drift between branches, environments and teammates.

---

## Table of contents

- [Why this bundle?](#why-this-bundle)
- [Compatibility matrix](#-compatibility-matrix)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [The two storage strategies](#-the-two-storage-strategies)
- [Workflows](#-workflows)
- [Commands reference](#-commands-reference)
- [The `#[Trigger]` attribute](#-the-trigger-attribute)
- [Multi-platform support](#-multi-platform-support)
- [Doctrine Migrations integration (optional)](#-doctrine-migrations-integration-optional)
- [Security](#-security)
- [Contributing](#-contributing)
- [License](#-license)

---

## Why this bundle?

Database triggers are powerful (cascading audit logs, denormalized counters, soft deletes, history tables, hierarchical constraints…) but they are **invisible from your application code**. They live somewhere in the DB, often only known to the dev who wrote them, and slowly drift between environments.

This bundle solves that:

- 📜 **Declarative mapping** via `#[Trigger]` attributes on your Doctrine entities — visible at code-review time
- ✅ **`triggers:schema:validate`** verifies the DB matches the mapping (perfect for CI)
- 🧙 **`make:trigger`** generates the boilerplate interactively
- 🔄 **`triggers:mapping:update`** imports existing legacy triggers into your codebase
- ✍️ **`triggers:schema:diff`** scaffolds the SQL/PHP files from your attributes
- 🚀 **`triggers:schema:update`** deploys local changes to the DB (dev/CI)
- 👀 **`triggers:schema:show`** previews the resolved SQL without touching the DB
- ⚙️ **Optional Doctrine Migrations integration** — `up`/`down` migration files generated automatically when you have `doctrine/doctrine-migrations-bundle` installed
- 🐘🐬🪟 **First-class support for PostgreSQL, MySQL/MariaDB and SQL Server**

---

## 📋 Compatibility

| Component | Supported |
|---|---|
| **PHP** | 8.2 · 8.3 · 8.4 |
| **Symfony** | 6.4 LTS · 7.x · 8.0 |
| **Doctrine ORM** | ^2.16 · ^3.0 (ready for ^4.0) |
| **Doctrine DBAL** | ^3.0 · ^4.0 |
| **Doctrine Migrations Bundle** | ^3.2 · ^4.0 — *optional, only required if `migrations: true`* |
| **PostgreSQL** | ≥ 12 (full feature parity from 14, automatic fallback for older versions) |
| **MySQL / MariaDB** | MySQL 5.7+ · MariaDB 10.5+ (10.11+ for STATEMENT-level triggers) |
| **SQL Server** | 2016+ (`CREATE OR ALTER`) — older versions partially supported |

The CI runs every push/PR against the **full matrix** (PHP × Symfony × Platform × ORM 2 vs 3 × DBAL 3 vs 4 × with/without `migrations-bundle`), so you can rely on what is announced above.

---

## 🚀 Installation

### 1. Install the bundle

```bash
composer require talleu/trigger-mapping
```

### 2. Register it in `config/bundles.php`

```php
return [
    // ...
    Talleu\TriggerMapping\Bundle\TriggerMappingBundle::class => ['all' => true],
];
```

That's it — the bundle is ready to use with sensible defaults.

### 3. *Optional* — install Doctrine Migrations Bundle

Only needed if you want trigger changes to be **automatically wired into Doctrine migrations** (`migrations: true` in the bundle config):

```bash
composer require doctrine/doctrine-migrations-bundle
```

Without it, the bundle works perfectly well: trigger files are still generated, and you can apply them via `triggers:schema:update --force` or by adding the SQL to your migrations manually.

---

## ⚙️ Configuration

The bundle ships with sane defaults — you don't need a config file to get started. To customize, create `config/packages/trigger_mapping.yaml`:

```yaml
trigger_mapping:
  storage:
    # 'php' (default) — generates PHP classes returning the SQL.
    # 'sql' — generates raw .sql files. Choose what fits your team's workflow.
    type: 'php'

    # Where the generated trigger files live.
    # Defaults to '%kernel.project_dir%/triggers'.
    directory: '%kernel.project_dir%/triggers'

    # PHP namespace for generated classes (only relevant when type: 'php').
    # Defaults to 'App\Triggers'.
    namespace: 'App\Triggers'

  # Generate Doctrine migrations automatically when the migrations-bundle is installed.
  # If migrations-bundle is missing, this option is silently ignored.
  migrations: true

  # Triggers you want the bundle to ignore at extraction & validation time
  # (typically: legacy triggers you do not own, third-party extensions, etc.).
  excludes:
    - audit_logs_trigger_we_dont_own
    - extension_xxx_trigger
```

📚 More: [docs/config.md](docs/config.md)

---

## 💾 The two storage strategies

You decide where the SQL of your triggers lives:

|  | **`storage.type: 'php'`** *(default)* | **`storage.type: 'sql'`** |
|---|---|---|
| **What gets generated** | A PHP class implementing `MySQLTriggerInterface`, `PostgreSQLTriggerInterface` or `SQLServerTriggerInterface` with `getTrigger()` / `getFunction()` | A `.sql` file (and a `functions/<name>.sql` file on PostgreSQL) |
| **Where it lives** | `App\Triggers\<TrgClassName>` (configurable) | `%kernel.project_dir%/triggers/<name>.sql` |
| **Best for** | Static analysis (PHPStan/Psalm), refactoring, IDE-friendly | DBAs editing raw SQL, `psql -f`-style workflows |
| **Migrations integration** | `addSql(\App\Triggers\Foo::getTrigger())` | `addSql(file_get_contents(__DIR__ . '/../triggers/foo.sql'))` |

You can also **override the global setting per-trigger** with `#[Trigger(storage: 'sql')]`.

📚 More: [docs/storing.md](docs/storing.md)

---

## 🔀 Workflows

### Scenario 1 — *"I have a legacy DB with triggers I want to bring under version control"*

```bash
bin/console triggers:mapping:update --apply --create-files
```

The command will:

1. List every trigger in the DB that has no `#[Trigger]` mapping yet
2. Find the matching Doctrine entity (incl. ManyToMany join tables)
3. Add the `#[Trigger]` attribute to that entity, with all params filled in from the DB
4. Generate the corresponding PHP class (or `.sql` file) with the actual SQL logic from the DB

Your triggers are now version-controlled and reviewable — you can edit them just like any code.

### Scenario 2 — *"I want to add a new trigger to my application"*

```bash
bin/console make:trigger
```

The interactive wizard asks for the entity, the trigger name, the events, the timing, etc., then:
- Adds `#[Trigger]` to the entity
- Generates the trigger boilerplate (class or `.sql` file)
- Optionally generates a Doctrine migration

Edit the generated `getTrigger()` body to put your real SQL, then run `triggers:schema:update --force` to deploy it (or run the migration in production).

### Scenario 3 — *"I changed an existing trigger and want to deploy it"*

Edit the generated PHP class (or `.sql` file) and choose your deployment path:

```bash
# Dev / CI: apply directly to the database
bin/console triggers:schema:update --force

# Production: generate a migration and run doctrine:migrations:migrate
bin/console triggers:schema:diff --apply
bin/console doctrine:migrations:migrate
```

### Scenario 4 — *"I want my CI to fail when DB and code drift apart"*

Just add this step to your CI pipeline:

```yaml
- run: bin/console triggers:schema:validate
```

If any trigger is missing in DB, missing in mapping, or has divergent parameters, the command exits with a non-zero status code and prints a clear table of the discrepancies.

---

## 📜 Commands reference

| Command | Alias | What it does |
|---|---|---|
| `triggers:schema:validate` | `t:s:v` | Compare mapping with DB and exit non-zero on drift. **Read-only**. |
| `triggers:schema:show` | `t:s:show` | Print the resolved SQL of mapped triggers. **Read-only, no DB connection used**. |
| `triggers:schema:diff` | `t:s:d` | Generate the missing trigger files / migrations from the mapping. |
| `triggers:schema:update` | `t:s:u` | Apply local trigger files to the DB (dry-run by default, use `--force`). |
| `triggers:mapping:update` | `t:m:u` | Import unmapped DB triggers into entity attributes. |
| `make:trigger` | — | Interactive wizard to scaffold a new trigger. Requires `symfony/maker-bundle` (dev). |

All commands accept `--entity App\Entity\Foo` to scope to a single entity.

### `triggers:schema:validate`

```bash
bin/console triggers:schema:validate
bin/console triggers:schema:validate --entity "App\Entity\User"
```

The "health check" for your trigger setup. **Read-only.** Reports four classes of problems:

- **Missing in DB**: trigger is mapped but not deployed → run a migration
- **Missing in mapping**: trigger exists in DB but is unknown to your code → run `triggers:mapping:update`
- **Mismatched parameters**: events / when / scope / function / table differ between attribute and DB
- **Unmapped DB tables**: DB triggers on tables that have no Doctrine entity → silently skipped (or use `excludes` to be explicit)

📚 More: [docs/schema_validate.md](docs/schema_validate.md)

### `triggers:schema:show`

```bash
bin/console triggers:schema:show
bin/console triggers:schema:show --entity "App\Entity\User"
```

Prints the SQL that *would* be applied for each mapped trigger — without touching the database. Useful for:
- Code reviews ("show me the actual SQL behind these attributes")
- CI logs (proof of what is deployed)
- Debugging (resolved file paths and class loading)

### `triggers:schema:diff`

```bash
bin/console triggers:schema:diff             # dry-run, lists what would be generated
bin/console triggers:schema:diff --apply     # actually creates the files / migration
bin/console triggers:schema:diff --apply --entity "App\Entity\User"
```

Code-first companion of `validate`: takes every `#[Trigger]` that has no DB counterpart and **scaffolds the file** (PHP class or `.sql`) plus, if `migrations: true`, a Doctrine migration. The generated body contains a `TODO` placeholder — you fill in the real SQL.

📚 More: [docs/schema_diff.md](docs/schema_diff.md)

### `triggers:schema:update`

```bash
bin/console triggers:schema:update                   # dry-run, shows the SQL
bin/console triggers:schema:update --force           # actually executes it
bin/console triggers:schema:update --force --entity "App\Entity\User"
```

Deploys the trigger logic from your local files **directly** to the DB. Safer than it looks:

- Asks for confirmation in `--force` mode
- Wraps the queries in a **transaction** on PostgreSQL and SQL Server (atomic deployment per trigger)
- Uses `CREATE OR REPLACE TRIGGER` on PG ≥ 14, falls back to `DROP IF EXISTS + CREATE` on PG < 14
- Drops & re-creates on MySQL (no DDL transactions there)
- Throws clear `CouldNotFindTriggerSqlFileException` / `NotAnValidTriggerClassException` when the source is missing or invalid

📚 More: [docs/schema_update.md](docs/schema_update.md)

### `triggers:mapping:update`

```bash
bin/console triggers:mapping:update                                  # dry-run
bin/console triggers:mapping:update --apply                          # only adds the #[Trigger] attribute
bin/console triggers:mapping:update --apply --create-files           # also creates the trigger files with the DB SQL
```

Inspects DB-side triggers without a mapping and **brings them into your codebase**. Detects:

- Direct table → entity matches via `getTableName()`
- ManyToMany join tables (sets `onTable: '<join_table>'` on the owning side)

Triggers on tables that don't belong to any Doctrine entity are skipped with a warning (so DB extensions don't pollute your mapping).

📚 More: [docs/mapping_update.md](docs/mapping_update.md)

### `make:trigger`

```bash
bin/console make:trigger
```

Interactive wizard powered by `symfony/maker-bundle`. Step-by-step prompts for:

- The entity to attach the trigger to
- Trigger name
- (PostgreSQL) Function name
- Events: `INSERT`, `UPDATE`, `DELETE`
- (PostgreSQL) Scope: `ROW` or `STATEMENT`
- Timing: `BEFORE` / `AFTER`
- Storage: `php` or `sql`
- (Optional) Generate a Doctrine migration

Validates each input early with friendly messages — invalid values throw `\InvalidArgumentException` with the list of allowed options.

📚 More: [docs/make_trigger.md](docs/make_trigger.md)

---

## 🎯 The `#[Trigger]` attribute

```php
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(
    name: 'trg_audit_user',          // SQL identifier (required)
    function: 'fn_audit_user',       // PostgreSQL function name (PG-only, otherwise null)
    on: ['INSERT', 'UPDATE'],        // INSERT, UPDATE, DELETE, TRUNCATE (PG-only)
    when: 'BEFORE',                  // BEFORE, AFTER, INSTEAD OF
    scope: 'ROW',                    // ROW, STATEMENT
    storage: 'php',                  // (optional) override the global storage type for this trigger
    className: UserAuditTrigger::class, // (optional) FQCN of the PHP trigger class
    onTable: 'user_role',            // (optional) explicit table name, e.g. for ManyToMany join tables
)]
class User { /* ... */ }
```

The attribute is `#[\Attribute(IS_REPEATABLE)]` so you can stack multiple triggers on the same entity:

```php
#[Trigger(name: 'trg_log_insert', on: ['INSERT'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_log_update', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
class User { /* ... */ }
```

### Validation rules (enforced at instantiation)

The constructor validates every value **eagerly** and throws `\InvalidArgumentException` on the spot — meaning typos surface at boot, not at runtime in production.

| Field | Rule |
|---|---|
| `name`, `function`, `onTable` | Match `/^[A-Za-z_][A-Za-z0-9_]{0,62}$/` (safe SQL identifier) |
| `when` | One of `BEFORE`, `AFTER`, `INSTEAD OF` (case-insensitive) |
| `scope` | One of `ROW`, `STATEMENT` (case-insensitive) |
| `on[]` | Each item: `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE` (case-insensitive) |
| `storage` | `php` or `sql` (or null to inherit the global setting) |

> **Why so strict?** Identifiers end up in generated SQL files, file paths and PHP migration source. The strict regex closes a wide range of attacks (SQL injection through `DROP TRIGGER`, path traversal in storage paths, PHP code injection in generated migrations) at the boundary, in one place. See [Security](#-security).

---

## 🐘🐬🪟 Multi-platform support

The bundle works the same on three platforms but each one has its quirks. Here is what is supported (and what is intentionally rejected):

|  | PostgreSQL | MySQL / MariaDB | SQL Server |
|---|---|---|---|
| `BEFORE` / `AFTER` | ✅ | ✅ | ❌ (rejected with a clear message) |
| `INSTEAD OF` | ✅ (on views) | ❌ | ✅ |
| Multi-events (`INSERT OR UPDATE OR DELETE`) | ✅ | ❌ (one event per trigger — rejected with a clear message) | ✅ |
| `ROW` / `STATEMENT` scope | ✅ | MySQL: ROW only · MariaDB ≥ 10.11: both | STATEMENT only (per-statement triggers) |
| `TRUNCATE` event | ✅ | ❌ | ❌ |
| Trigger functions | ✅ (separate `pg_proc`) | n/a (body inlined in trigger) | n/a (body inlined in trigger) |
| Multi-schema | ✅ (`current_schemas(false)`) | n/a | ✅ (`SCHEMA_NAME()`) |
| FK / partition triggers filtering | ✅ (`tgconstraint = 0`, `tgparentid = 0`) | n/a | ✅ (`parent_class = 1`) |
| `CREATE OR REPLACE TRIGGER` | PG ≥ 14 (auto-fallback to `DROP + CREATE` below) | DROP + CREATE | `CREATE OR ALTER` (SQL Server ≥ 2016) |
| Transactional deploys | ✅ | ❌ (DDL implicit commits) | ✅ |

### How extraction works

PostgreSQL triggers are decoded from the **`pg_trigger.tgtype` bitfield** — exact, no text parsing, no fragile regex. SQL Server reads `sys.triggers` + `sys.trigger_events` + `sys.sql_modules.definition` (scoped to `SCHEMA_NAME()`). MySQL/MariaDB reads `information_schema.TRIGGERS` (incl. `ACTION_ORIENTATION` for STATEMENT support).

---

## ⚙️ Doctrine Migrations integration (optional)

When `doctrine/doctrine-migrations-bundle` is installed and `migrations: true` is set in the bundle config, **every** `triggers:schema:diff --apply` and `make:trigger --migration` automatically generates a Doctrine migration with both the `up()` and `down()` statements:

```php
// Auto-generated Version20260101000000.php (excerpt)
public function up(Schema $schema): void
{
    $this->addSql(\App\Triggers\TrgUserUpdatedAt::getFunction());
    $this->addSql(\App\Triggers\TrgUserUpdatedAt::getTrigger());
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TRIGGER IF EXISTS trg_user_updated_at ON user;');
    $this->addSql('DROP FUNCTION IF EXISTS fn_update_timestamp();');
}
```

Then deploy with the standard `bin/console doctrine:migrations:migrate` workflow — your triggers are part of the same atomic deployment as the rest of your schema changes.

> ⚠️ The generated SQL strings are produced via `var_export()` — which means even an attacker-controlled trigger name (e.g. coming from a compromised legacy DB) cannot break out of the string literal and inject PHP code into your migration files.

---

## 🛡️ Security

The bundle takes adversarial inputs seriously. Several mitigations are in place by default:

- **Strict identifier validation** at the `#[Trigger]` attribute boundary (regex `^[A-Za-z_][A-Za-z0-9_]{0,62}$`), as well as on data **extracted from the database** in `TriggersDbExtractor`. Names that don't match are skipped — they never reach the file system or the migration generator.
- **`var_export()`** is used to serialise SQL into PHP migration source code, so no characters can break out of the string literal.
- **`pdo_sqlsrv` & ODBC validation** of trigger metadata via parameterized queries / typed columns rather than string concatenation.
- **Path constraints**: trigger names are also used as filesystem path components — invalid names cannot escape the configured `storage.directory`.
- **Confirmation prompt** in `triggers:schema:update --force`, transaction wrapping on PG/SQL Server.

If you find a security issue, please report it privately via [GitHub Security Advisories](https://github.com/clementtalleu/trigger-mapping/security/advisories) rather than a public issue.

---

## 🤝 Contributing

Contributions are very welcome. The full contribution guide — including how to run the test suite locally with Docker, how to install `pdo_sqlsrv` for SQL Server, how to use the `Makefile` shortcuts — is in [CONTRIBUTING.md](CONTRIBUTING.md).

Quick start:

```bash
git clone https://github.com/clementtalleu/trigger-mapping.git
cd trigger-mapping
composer install
make docker-up      # spawn MySQL, PostgreSQL, SQL Server containers
make test-docker    # run the full test suite (unit + 3 platforms) inside a php container
```

The CI runs every push/PR against:

- **Unit tests** (PHP 8.2/8.3/8.4 × Symfony 6.4/7.4/8.0)
- **Functional tests** (× MySQL/PostgreSQL/SQL Server)
- **Legacy Doctrine** (ORM ^2.16 + DBAL ^3)
- **Without `doctrine-migrations-bundle`** (verifies the bundle stays usable when the optional dep is missing)
- **PHPStan level 8** + **PHP-CS-Fixer**

---

## 📄 License

[MIT](LICENSE) © Clément Talleu and contributors.
