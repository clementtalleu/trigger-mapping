<?php

namespace Talleu\TriggerMapping\Tests\Application\Triggers;

use Talleu\TriggerMapping\Contract\MySQLTriggerInterface;

class SqlServerTriggerClass implements MySQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE OR ALTER TRIGGER trg_update_schema_test
            ON sql_server_update_schema_test_entity
            AFTER INSERT 
            AS BEGIN
                -- La logique d'un trigger SQL Server va ici.
                -- Voici un exemple simple et valide :
                SELECT 'Sample Instead of trigger' as [Message]
            END
        SQL;
    }
}