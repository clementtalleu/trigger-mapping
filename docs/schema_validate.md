### ✅ Validate Schema Synchronization (`triggers:schema:validate`)

```
bin/console triggers:schema:validate
```

This command is your primary tool for ensuring that your application's trigger mappings are in sync with the actual database schema. It acts as a health check for your trigger setup.

The validator performs a comprehensive comparison between the triggers defined via `#[Trigger]` attributes on your entities and the triggers that actually exist in the database. It will report on three types of discrepancies:

1.  **Missing in Database:** Triggers that are mapped in your code but do not exist in the database.
2.  **Missing in Mapping:** Triggers that exist in the database but are not mapped in your code.
3.  **Mismatched Parameters:** Triggers that exist in both places but have different parameters (e.g., a different `timing`, `scope`, or target `function`).

This command is purely **informational**; it will never modify your code or your database schema. Its purpose is to provide a clear and detailed report of any inconsistencies.

#### Filtering by Entity
You can also narrow down the validation to a single entity by using the --entity option to focus on a specific part of your schema. You must provide the Full-Qualified Class Name (FQCN) of the entity.
```
php bin/console triggers:schema:validate --entity='App\Entity\User'
```

#### Continuous Integration (CI)

This command is ideal for integration into your CI pipeline. By running it as part of your test suite, you can automatically prevent schema drifts from being merged, ensuring that your database and your application code always remain synchronized.

> **Note:** While the command performs a thorough check of the trigger's metadata (name, table, events, timing, scope, and associated function), it does not perform a deep comparison of the SQL logic inside the trigger body or function. This level of validation already provides a very high degree of confidence in your schema's integrity.
