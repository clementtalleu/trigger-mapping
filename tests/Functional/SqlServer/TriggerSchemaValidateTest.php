<?php

namespace Talleu\TriggerMapping\Tests\Functional\SqlServer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\MissingInDbEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\SqlServerCorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\TriggerBadParamsEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggerValidateSchemaTestCase;

final class TriggerSchemaValidateTest extends AbstractTriggerValidateSchemaTestCase
{
    protected function getCreateTriggerSql(string $triggerName, string $tableName, string $when, string $events): string
    {
        return <<<SQL
            CREATE OR ALTER TRIGGER {$triggerName}
            ON {$tableName}
            AFTER {$events} 
            AS BEGIN
                -- Dummy logic for test trigger
                SELECT 'Sample Instead of trigger' as [Message]
            END
        SQL;
    }

    public function testCorrectlyMappedEntity(): void
    {
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'sql_server_correctly_mapped_entity',
            'AFTER',
            'UPDATE',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => SqlServerCorrectlyMappedEntity::class]);
        print_r($commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('The database triggers are in sync with the mapping.', $commandTester->getDisplay());
    }

    public function testMissingIndDb(): void
    {
        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => MissingInDbEntity::class]);

        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'not sync with the current mapping'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'missing_in_db_trigger'));
    }

    public function testBadParams(): void
    {
        $sql = $this->getCreateTriggerSql(
            'bad_params_trigger',
            'trigger_bad_params_entity',
            'AFTER',
            'INSERT',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => TriggerBadParamsEntity::class]);
        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);

        $this->assertTrue(str_contains($commandTester->getDisplay(), 'not sync with the current mapping'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'parameters that do not match the database'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'bad_params_trigger'));
    }

    public function testMissingInMapping(): void
    {
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'no_trigger_entity',
            'AFTER',
            'UPDATE',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => NoTriggerEntity::class]);

        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'not sync with the current mapping'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'not mapped'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'correctly_mapped_trigger'));
    }

    public function testTableWithoutEntity(): void
    {
        $this->executeSql("CREATE TABLE useless_table (name VARCHAR(255) NOT NULL);");

        $sql = $this->getCreateTriggerSql(
            'useless_trigger',
            'useless_table',
            'AFTER',
            'UPDATE',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertTrue(str_contains($commandTester->getDisplay(), 'useless_trigger concerns a table'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'useless_table'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'Doctrine'));
    }
}