<?php

namespace Talleu\TriggerMapping\Tests\Functional\Postgresql;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\CorrectlyMappedEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\MissingInDbEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\PostgresqlMultiEventsEntity;
use Talleu\TriggerMapping\Tests\Application\Entity\TriggerBadParamsEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggerValidateSchemaTestCase;

final class TriggerSchemaValidateTest extends AbstractTriggerValidateSchemaTestCase
{
    protected function getCreateTriggerSql(string $triggerName, string $tableName, string $when, string $events, string $functionName): string
    {
        $functionSql = <<<SQL
            CREATE OR REPLACE FUNCTION {$functionName}() RETURNS trigger AS $$
            BEGIN
                -- Dummy function for tests
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL;

        $triggerSql = "CREATE TRIGGER {$triggerName} {$when} {$events} ON {$tableName} FOR EACH ROW EXECUTE FUNCTION {$functionName}()";

        return $functionSql . ';' . PHP_EOL . $triggerSql;
    }

    protected function createExcludedTrigger(string $triggerName): void
    {
        $this->executeSql(<<<SQL
            CREATE OR REPLACE FUNCTION fn_excluded() RETURNS trigger AS \$\$
            BEGIN RETURN NEW; END;
            \$\$ LANGUAGE plpgsql
        SQL);
        $this->executeSql(
            "CREATE TRIGGER {$triggerName} AFTER INSERT ON correctly_mapped_entity ".
            'FOR EACH ROW EXECUTE FUNCTION fn_excluded()'
        );
    }

    public function testCorrectlyMappedEntity(): void
    {
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'correctly_mapped_entity',
            'BEFORE',
            'UPDATE',
            'correct_func'
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => CorrectlyMappedEntity::class]);
        $commandTester->assertCommandIsSuccessful();

        $this->assertTrue(str_contains($commandTester->getDisplay(), 'are in sync with the mapping'));
    }

    public function testBadParams(): void
    {
        $sql = $this->getCreateTriggerSql(
            'bad_params_trigger',
            'trigger_bad_params_entity',
            'AFTER',
            'INSERT',
            'func_name'
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

    public function testMissingIndDb(): void
    {
        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => MissingInDbEntity::class]);
        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'not sync with the current mapping'));
        $this->assertTrue(str_contains($commandTester->getDisplay(), 'missing_in_db_trigger'));
    }

    public function testMissingInMapping(): void
    {
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'no_trigger_entity',
            'BEFORE',
            'UPDATE',
            'correct_func'
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

    public function testWhenMismatchIsReportedSpecifically(): void
    {
        // Entity expects BEFORE UPDATE; we create the trigger as AFTER UPDATE
        // and assert the validate command pinpoints `when` as the divergent field.
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'correctly_mapped_entity',
            'AFTER',
            'UPDATE',
            'correct_func',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => CorrectlyMappedEntity::class]);
        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);

        $output = $commandTester->getDisplay();
        $this->assertTrue(str_contains($output, 'when'), 'mismatch parameter column must mention "when"');
        $this->assertTrue(str_contains($output, 'BEFORE'), 'expected value (BEFORE) must be shown');
        $this->assertTrue(str_contains($output, 'AFTER'), 'actual value (AFTER) must be shown');
    }

    public function testFunctionMismatchIsReportedSpecifically(): void
    {
        // Entity expects function "correct_func" but the DB trigger references "wrong_func".
        $sql = $this->getCreateTriggerSql(
            'correctly_mapped_trigger',
            'correctly_mapped_entity',
            'BEFORE',
            'UPDATE',
            'wrong_func',
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => CorrectlyMappedEntity::class]);
        $this->assertEquals($commandTester->getStatusCode(), Command::FAILURE);

        $output = $commandTester->getDisplay();
        $this->assertTrue(str_contains($output, 'function'));
        $this->assertTrue(str_contains($output, 'correct_func'));
        $this->assertTrue(str_contains($output, 'wrong_func'));
    }

    public function testMultiEventsTriggerIsCorrectlyExtracted(): void
    {
        // Regression: this is the exact case the old text-based parser couldn't
        // handle. With the bitfield-based extraction the three events are
        // surfaced and validate must report the trigger as in sync.
        $this->createSchemaForEntities([PostgresqlMultiEventsEntity::class]);

        $this->executeSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_multi_events() RETURNS trigger AS $$
            BEGIN RETURN NEW; END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->executeSql(
            'CREATE TRIGGER trg_multi_events BEFORE INSERT OR UPDATE OR DELETE '
            .'ON postgresql_multi_events_entity FOR EACH ROW EXECUTE FUNCTION fn_multi_events()'
        );

        $command = $this->application->find('triggers:schema:validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--entity' => PostgresqlMultiEventsEntity::class]);
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('in sync with the mapping', $commandTester->getDisplay());
    }

    public function testTableWithoutEntity(): void
    {
        $this->executeSql("CREATE TABLE useless_table (name VARCHAR(255) NOT NULL);");

        $sql = $this->getCreateTriggerSql(
            'useless_trigger',
            'useless_table',
            'BEFORE',
            'UPDATE',
            'correct_func'
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