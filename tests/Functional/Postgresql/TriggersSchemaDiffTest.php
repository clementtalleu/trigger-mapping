<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional\Postgresql;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\PostgresqlSqlDiffEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersSchemaDiffTestCase;

final class TriggersSchemaDiffTest extends AbstractTriggersSchemaDiffTestCase
{
    public function testDryRunListsTriggerWhenMappedButMissing(): void
    {
        $this->createSchemaForEntities([PostgresqlSqlDiffEntity::class]);

        $output = $this->runDiff(['--entity' => PostgresqlSqlDiffEntity::class]);

        self::assertStringContainsString('mapped but missing from the database', $output);
        self::assertStringContainsString('trg_sql_diff_test', $output);
        self::assertStringContainsString('To create these files', $output);
        self::assertDirectoryDoesNotExist($this->triggersDir.'/triggers');
    }

    public function testApplyModeGeneratesSqlFiles(): void
    {
        $this->createSchemaForEntities([PostgresqlSqlDiffEntity::class]);

        $output = $this->runDiff([
            '--entity' => PostgresqlSqlDiffEntity::class,
            '--apply' => true,
        ]);

        self::assertStringContainsString('Trigger files created successfully', $output);
        self::assertFileExists($this->triggersDir.'/triggers/trg_sql_diff_test.sql');
        self::assertFileExists($this->triggersDir.'/functions/fn_sql_diff_test.sql');
    }

    public function testNothingToDoWhenTriggerExistsInDb(): void
    {
        $this->createSchemaForEntities([PostgresqlSqlDiffEntity::class]);

        $this->connection->executeStatement(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_sql_diff_test() RETURNS trigger AS $$
            BEGIN RETURN NEW; END;
            $$ LANGUAGE plpgsql
        SQL);
        $this->connection->executeStatement(
            'CREATE TRIGGER trg_sql_diff_test AFTER INSERT ON postgresql_sql_diff_entity '
            .'FOR EACH ROW EXECUTE FUNCTION fn_sql_diff_test()'
        );

        $output = $this->runDiff(['--entity' => PostgresqlSqlDiffEntity::class]);

        self::assertStringContainsString('All mapped triggers already exist', $output);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function runDiff(array $args): string
    {
        $command = $this->application->find('triggers:schema:diff');
        $tester = new CommandTester($command);
        $tester->execute($args);

        return $tester->getDisplay();
    }
}
