<?php

namespace Talleu\TriggerMapping\Tests\Application\Triggers;

use Talleu\TriggerMapping\Contract\MySQLTriggerInterface;

class MysqlTriggerClass implements MySQLTriggerInterface
{
    public static function getTrigger(): string
    {
        return <<<SQL
            CREATE TRIGGER trg_update_schema_test
            AFTER INSERT ON mysql_update_schema_test_entity
            FOR EACH ROW
            BEGIN
                -- La logique d'un trigger MySQL va ici.
                -- Voici un exemple simple et valide :
                SET @dummy_var = 1;
            END
        SQL;
    }
}