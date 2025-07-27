## ✍️ Create Trigger Files from Mapping 

```
bin/console triggers:schema:diff --apply
```

This command follows a "code-first" approach. It's the primary tool for when you have defined your triggers in your entity files using #[Trigger] attributes and now need to generate the corresponding template files based on that mapping.

The command works by performing a "diff": it reads all your existing mappings and compares them against the database schema. For every trigger that is mapped in your code but is missing from the database, it will generate the necessary boilerplate files. This includes creating the trigger file itself, and for PostgreSQL, the associated function file.


#### File and Migration Generation

Based on your bundle's configuration, this command will:
* Create the trigger logic files (either as `.sql` files or PHP classes).
* If `migrations` is set to `true` in your configuration, it will also automatically generate a new Doctrine migration file to apply these new triggers to your database.

> **Important Note:** This command only creates the *scaffolding* for your triggers. It generates the files with a `TODO` placeholder inside. You are still responsible for the most important part: writing the actual SQL logic within the generated files to meet your application's needs. The command does not apply the triggers directly to the database; you must run the generated migration for that or triggers:schema:update then.
