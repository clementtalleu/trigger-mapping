### Configuration Parameters Explained

#### `storage.type`

This option determines the strategy for storing your trigger logic.

* **`sql` :** This is the recommended approach. The bundle will generate raw `.sql` files for your triggers and functions in the specified directory. This keeps your database logic in pure SQL.
* **`php` (default) :** This strategy generates PHP classes that implement an interface. These classes contain static methods (`getTrigger()`, `getFunction()`) that return the SQL code as strings. This allows you to keep your trigger logic version-controlled within your application's `src` directory.

#### `storage.directory`

Specifies the directory where the generated trigger files (either `.sql` or `.php`) will be stored. By default, it points to a `triggers` directory at the root of your project. You can change this to any path you prefer, for example, to place SQL files alongside your Doctrine migrations: `%kernel.project_dir%/migrations/triggers`.

#### `storage.namespace`

This option is only used when `storage.type` is set to `php`. It defines the PHP namespace for the generated trigger classes. The default is `App\Trigger`, which will result in classes being created in the `src/Trigger/` directory.

#### `migrations`

* **`true` (default):** When enabled, generate a new Doctrine migration file to apply the trigger to your database. This is the recommended setting for a seamless workflow.
* **`false`:** If you prefer to manage your database schema changes manually or with a different tool, you can disable automatic migration generation. The trigger logic files will still be created, but you will be responsible for creating the migration to apply them.
