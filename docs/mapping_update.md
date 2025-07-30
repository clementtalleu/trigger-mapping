### 🔄 Synchronize Mappings from Database (`triggers:mapping:update`)

```
bin/console triggers:mapping:update --apply --create-files
```

This command inspects all triggers present in your database that are not yet mapped in your entities. For each one, it finds the appropriate Doctrine entity class and adds the correct `#[Trigger]` attribute with all its parameters (`name`, `on`, `when`, etc.) filled in from the schema.

#### Join Table Handling

The command is also able to handle triggers on tables that do not have a dedicated Doctrine entity, such as many-to-many join tables. In this case, it will find one of the entities involved in the relationship and add the `#[Trigger]` attribute to its class. To ensure the trigger is correctly associated, it will automatically populate the `onTable` property of the attribute with the name of the join table.

#### Modes of Operation

The command is designed to be safe and predictable, offering several modes of operation via its options:

* **Dry-Run Mode (Default):** By default, running the command will only display a report of the mappings it *would* create. No files on your disk will be modified. This allows you to review the planned changes safely.
* **Apply Mode (`--apply`):** When you use this option, the command will perform the actions described in the dry-run. It will modify your entity files to add the missing `#[Trigger]` attributes.
* **File Creation Mode (`--create-files`):** This option does everything the `--apply` mode does, but goes one step further. It will also generate the corresponding PHP class or `.sql` file for each new mapping, populating it with the actual trigger logic extracted directly from your database. This is perfect for "reverse-engineering" your existing triggers into your version-controlled codebase.
