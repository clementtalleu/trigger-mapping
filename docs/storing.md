## Storing Trigger Logic: SQL Files vs. PHP Classes

The bundle offers two distinct strategies for managing the logic of your triggers, configured via the `storage.type` parameter. You can choose between raw `.sql` files or dedicated PHP classes. This flexibility allows you to adopt the workflow that best suits your team and project needs.

### 1. PHP Class Storage (php)

This is the default approach, you can encapsulate your trigger logic within dedicated PHP classes. When you choose this storage type, the bundle generates a PHP class that returns the SQL statements via static methods.
This approach brings your trigger logic directly into your application's codebase, allowing it to be version-controlled with Git and potentially integrated into your testing workflow. It's a great way to treat your database schema as an integral part of your application.

```php
namespace App\Trigger;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class TrgUserUpdatedAt implements PostgreSQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE TRIGGER trg_user_updated_at BEFORE UPDATE ON "user"
            FOR EACH ROW
            EXECUTE FUNCTION update_timestamp_func();
        SQL;
    }

    public static function getFunction(): string
    {
        return <<<SQL
            CREATE OR REPLACE FUNCTION update_timestamp_func()
            RETURNS trigger AS $$
            BEGIN
                -- TODO: Add your PostgreSQL logic here
                -- Example: NEW.updated_at := NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL;
    }
}
```

### 2. SQL File Storage (`sql`)

Alternatively when you choose this storage type, the bundle generates standard `.sql` files containing the `CREATE FUNCTION` or `CREATE TRIGGER` statements.

This method is ideal if you prefer to keep your database logic in pure SQL, making it easy for DBAs to review or for use in standard database tooling.

**Example of generated files for a PostgreSQL trigger:**

A file for the function (`/triggers/functions/update_timestamp_func.sql`):
```sql
CREATE OR REPLACE FUNCTION update_timestamp_func()
RETURNS trigger AS $$
BEGIN
    -- TODO: Add your PostgreSQL logic here
    -- Example: NEW.updated_at := NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

And a file for the trigger itself (/triggers/triggers/trg_user_updated_at.sql):
```
CREATE TRIGGER trg_user_updated_at
    BEFORE UPDATE ON "user"
    FOR EACH ROW
    EXECUTE PROCEDURE update_timestamp_func();
```

For mysql/mariadb platform, it will only create the trigger which you can fill in with your own logic
