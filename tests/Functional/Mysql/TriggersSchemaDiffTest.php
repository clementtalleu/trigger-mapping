<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional\Mysql;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\MysqlSqlDiffEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersSchemaDiffTestCase;

final class TriggersSchemaDiffTest extends AbstractTriggersSchemaDiffTestCase
{
    public function testDryRunListsTriggerWhenMappedButMissing(): void
    {
        $this->createSchemaForEntities([MysqlSqlDiffEntity::class]);

        $output = $this->runDiff(['--entity' => MysqlSqlDiffEntity::class]);

        self::assertStringContainsString('mapped but missing from the database', $output);
        self::assertStringContainsString('trg_sql_diff_test', $output);
        self::assertDirectoryDoesNotExist($this->triggersDir);
    }

    public function testApplyModeGeneratesSqlFile(): void
    {
        $this->createSchemaForEntities([MysqlSqlDiffEntity::class]);

        $output = $this->runDiff([
            '--entity' => MysqlSqlDiffEntity::class,
            '--apply' => true,
        ]);

        self::assertStringContainsString('Trigger files created successfully', $output);
        self::assertFileExists($this->triggersDir.'/trg_sql_diff_test.sql');
    }

    public function testNothingToDoWhenTriggerExistsInDb(): void
    {
        $this->createSchemaForEntities([MysqlSqlDiffEntity::class]);

        $this->connection->executeStatement(
            'CREATE TRIGGER trg_sql_diff_test AFTER INSERT ON mysql_sql_diff_entity '
            .'FOR EACH ROW BEGIN SET @noop = 1; END'
        );

        $output = $this->runDiff(['--entity' => MysqlSqlDiffEntity::class]);

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
