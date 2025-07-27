<?php

namespace Talleu\TriggerMapping\Tests\Functional\Mysql;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\MissingInDbEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MysqlCorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\TriggerBadParamsEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggerValidateSchemaTestCase;

final class TriggerSchemaValidateTest extends AbstractTriggerValidateSchemaTestCase
{
    protected function getCreateTriggerSql(string $triggerName, string $tableName, string $timing, string $events): string
    {
        return <<<SQL
            CREATE TRIGGER {$triggerName}
            {$timing} {$events} ON {$tableName}
            FOR EACH ROW
            BEGIN
                -- Dummy logic for test trigger
                SET @dummy_var = 1;
            END;
        SQL;
    }

    public function testCorrectlyMappedEntity(): void
    {
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'mysql_correctly_mapped_entity',
            'BEFORE',
            'UPDATE',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => MysqlCorrectlyMappedEntity::class]);
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
            'BEFORE',
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
}