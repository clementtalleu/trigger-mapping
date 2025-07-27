## 🚀 Apply Local Changes to the Database (`triggers:schema:update`)

```
bin/console triggers:schema:update --force
```

This command is the final step to deploy your trigger logic. Its purpose is to take the trigger definitions from your local files (either `.sql` files or PHP classes) and execute them directly against the database.

It works by reading all your `#[Trigger]` mappings, finding the associated source file for each one, and running the SQL content. This command is the counterpart to `triggers:schema:diff`, allowing you to apply the changes you've defined in your code.

#### Modes of Operation

To prevent accidental changes, the command operates in two modes:

* **Dry-Run Mode (Default):** When run without any options, the command will perform a "dry-run". It will display all the SQL queries that it *would* execute to synchronize the schema, but it will **not** make any changes to your database. This is the safe default for reviewing the pending updates.

* **Force Mode (`--force`):** To actually apply the changes, you must use the `--force` option. The command will execute the SQL queries against your database. For safety, it will ask for a final confirmation before modifying your schema.
* **Entity path (`--entity=App\Entity\User`):** to update the schema for a given entity.

This command is particularly useful in local development for applying changes quickly without creating a migration, or as part of a deployment strategy where you manage schema updates directly.
