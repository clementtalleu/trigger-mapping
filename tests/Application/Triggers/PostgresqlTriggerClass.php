<?php

namespace Talleu\TriggerMapping\Tests\Application\Triggers;

use Talleu\TriggerMapping\Contract\PostgreSQLTriggerInterface;

class PostgresqlTriggerClass implements PostgreSQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE TRIGGER trg_update_schema_test AFTER INSERT ON update_schema_test_entity FOR EACH ROW EXECUTE FUNCTION fn_update_schema_test()
        SQL;
    }

    public static function getFunction(): string
    {
        return <<<SQL
            CREATE OR REPLACE FUNCTION fn_update_schema_test()
            RETURNS trigger AS $$
                BEGIN
                -- nothing
                RETURN NEW;
            END;        
            $$ LANGUAGE plpgsql;
        SQL;
    }
}
