# 🧩 Trigger Mapping Bundle


**Bring your database [triggers](https://sql.sh/cours/create-trigger) into your Doctrine entities. Map, validate, and generate SQL triggers templates with PHP attributes.**

This makes your schema declarative, easy to read, and version-controlled alongside your application code.

```php
namespace App\Entity;

use Talleu\TriggerMapping\Attribute\Trigger;
use App\Triggers\MyAwesomeTrigger;

#[Trigger(
    name: 'trg_user_updated_at',
    when: 'AFTER',
    on: ['INSERT', 'UPDATE'],
    function: 'fn_update_timestamp_func',
    className: MyAwesomeTrigger::class
)]
class User
{
    // ...
}
```

## Features ✨

* **Declarative Mapping:** 📜 Use simple `#[Trigger]` attributes on your Doctrine entities to define and document your database triggers.

* **Schema Validation:** ✅ A `triggers:schema:validate` command to ensure your mapped triggers are in sync with your database schema.

* **Code Generation:** 🧙‍♂️ Powered by the Symfony MakerBundle, the `bin/console make:trigger` command interactively generates trigger code templates (either as PHP classes or raw `.sql` files) and adds the corresponding attribute to your entity.

* **Synchronization Tools:** 🔄 Automatically create missing mappings from existing database triggers with `bin/console triggers:mapping:update`.

* **Schema comparison:** ✍️ The `triggers:schema:diff` command creates the necessary SQL/PHP template files for triggers that are mapped in your entities but are missing from the database schema. This is the perfect way to "scaffold" your trigger files after defining them in your code.

* **Schema Deployment:** 🚀 The `triggers:schema:update` command directly applies the trigger logic from your local PHP/SQL files to the database, perfect for development or for deploying changes without creating a migration.

* **Doctrine Migrations Integration:** ⚙️ Automatically generate migration files for your triggers, making your deployment process safe and repeatable.

* **Multi-Platform Support:** 🐘🐬 Designed to work seamlessly with both PostgreSQL and MySQL/MariaDB.

This bundle is designed to bridges the gap between your application's domain logic and your database's powerful trigger capabilities, making your development workflow smoother and your database schema more robust.


## Installation 🚀

### Step 1: Download the Bundle

Enter your project directory and execute the following command to download the latest stable version of this bundle:

```
composer require talleu/trigger-mapping
```

### Step 2: Enable the Bundle

Check that the bundle is registered in your `config/bundles.php` file.

```php
return [
    Talleu\TriggerMapping\Bundle\TriggerMappingBundle::class => ['all' => true],
];
```


## Configuration

You can (or not) customize its behavior by creating a configuration file.
Create a new file `config/packages/trigger_mapping.yaml` and add the following content to get started:

```yaml
# config/packages/trigger_mapping.yaml
trigger_mapping:
  # --- Storage Configuration ---
  # Defines how and where your trigger logic files are stored.
  storage:
    # 'sql': Generates raw .sql files.
    # 'php': Generates PHP classes that return SQL statements. (default).
    type: 'php'

    # The directory where trigger files will be generated.
    # Defaults to '%kernel.project_dir%/triggers'.
    directory: '%kernel.project_dir%/triggers'

    # The namespace for the generated PHP classes when using the 'php' storage type.
    # Defaults to 'App\Triggers'.
    namespace: 'App\Triggers'

  # --- Migrations ---
  # Whether to automatically generate Doctrine migrations for your triggers.
  # Set to false if you want to manage migrations manually.
  migrations: true
```

More infos about configuration [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/config.md)


## Storing Trigger Logic: SQL Files vs. PHP Classes

The bundle offers two distinct strategies for managing the logic of your triggers, configured via the `storage.type` parameter. You can choose between raw `.sql` files or dedicated PHP classes. This flexibility allows you to adopt the workflow that best suits your team and project needs.

More infos about types of storage [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/storing.md)

---

## 🔀 Basic Usage workflows

Here are a few common scenarios to help you get started and understand the main workflow of the bundle.

### Scenario 1: Integrating an Existing Project with Triggers

You have an existing project with several triggers already active in your database, but they are not yet managed by the bundle. Your goal is to bring them into your version-controlled codebase.
Just execute
```bash
php bin/console triggers:mapping:update --apply --create-files
```

It will directly :
* Find the correct Doctrine entity for each unmapped trigger.
* Add the `#[Trigger]` attribute to the entity class.
* Create the corresponding PHP class (or .sql file).
* Fill that file with the actual SQL logic extracted from your database.

After running this command, your existing triggers are now fully integrated into your project, version-controlled, and ready to be modified.

---
### Scenario 2: Modifying an Existing Trigger

You need to change the behavior of a trigger that is already mapped and managed by the bundle.

1.  **Locate and Modify the Logic:** Find the trigger's logic file (either in your `triggers/` directory or in `src/Trigger/`). Open it and make your desired changes to the SQL code.

2.  **Apply the Changes:** You have two options to deploy your modifications to the database:

    * **Option A: Direct Update**
      The `schema:update` command is the fastest way to apply your local changes. It reads the content of your modified file and executes it directly against the database.
        ```bash
        # First, run a dry-run to see the SQL that will be executed
        php bin/console triggers:schema:update

        # Then, apply the changes
        php bin/console triggers:schema:update --force
        ```

    * **Option B: Using a Doctrine Migration (better for production)**
      Creating a Doctrine migration, and just fill it with the right path to your SQL logic.
        ```php
        $this->addSql(MyUpdatedTriggerClass::getFunction());
        $this->addSql(MyUpdatedTriggerClass::getTrigger());
        ```
      Then run `doctrine:migrations:migrate` to apply your changes.

---

## 📋 Availables commands :


### ✅ Validate Schema Synchronization

```
bin/console triggers:schema:validate
```

This command is your primary tool for ensuring that your application's trigger mappings are in sync with the actual database schema. It acts as a health check for your trigger setup.

Dedicated documentation [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/schema_validate.md)


---
> **⚠️** All the following commands involve file generation and require symfony/maker-bundle (dev env only) to be installed.


### 🔄 Synchronize Mappings from Database

```
bin/console triggers:mapping:update --apply --create-files
```

This command inspects all triggers present in your database that are not yet mapped in your entities. For each one, it finds the appropriate Doctrine entity class and adds the correct `#[Trigger]` attribute with all its parameters (`name`, `on`, `when`, etc.) filled in from the schema.

Dedicated documentation [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/mapping_update.md)

--- 
## ✍️ Create Trigger Files from Mapping 

```
bin/console triggers:schema:diff --apply
```

This command follows a "code-first" approach. It's the primary tool for when you have defined your triggers in your entity files using #[Trigger] attributes and now need to generate the corresponding template files based on that mapping.

Dedicated documentation [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/schema_diff.md)


--- 
## 🚀 Apply Local Changes to the Database 

```
bin/console triggers:schema:update --force
```

This command is the final step to deploy your trigger logic. Its purpose is to take the trigger definitions from your local files (either `.sql` files or PHP classes) and execute them directly against the database.

Dedicated documentation [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/schema_update.md)


---
## 🧙‍♂️ Create a New Trigger Interactively

This command, powered by the Symfony MakerBundle, is your go-to tool for creating a new trigger from scratch. It launches an interactive wizard that guides you through the entire process, step-by-step.

```
bin/console make:trigger
```

Dedicated documentation [here](https://github.com/clementtalleu/trigger-mapping/blob/main/docs/make_trigger.md)

---

### Exclude triggers

Maybe you need to excludes some triggers in your DB from your current mapping.
Just modify your configuration file by adding

```yaml
trigger_mapping:
 #...
  excludes: 
    - excluded_trigger_name
    - another_excluded_trigger_name
```

It allows you to ignore theses trigger