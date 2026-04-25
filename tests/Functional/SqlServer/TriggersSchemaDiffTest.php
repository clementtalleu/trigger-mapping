<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional\SqlServer;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\SqlServerSqlDiffEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersSchemaDiffTestCase;

final class TriggersSchemaDiffTest extends AbstractTriggersSchemaDiffTestCase
{
    public function testDryRunListsTriggerWhenMappedButMissing(): void
    {
        $this->createSchemaForEntities([SqlServerSqlDiffEntity::class]);

        $output = $this->runDiff(['--entity' => SqlServerSqlDiffEntity::class]);

        self::assertStringContainsString('mapped but missing from the database', $output);
        self::assertStringContainsString('trg_sql_diff_test', $output);
        self::assertDirectoryDoesNotExist($this->triggersDir);
    }

    public function testApplyModeGeneratesSqlFile(): void
    {
        $this->createSchemaForEntities([SqlServerSqlDiffEntity::class]);

        $output = $this->runDiff([
            '--entity' => SqlServerSqlDiffEntity::class,
            '--apply' => true,
        ]);

        self::assertStringContainsString('Trigger files created successfully', $output);
        self::assertFileExists($this->triggersDir.'/trg_sql_diff_test.sql');
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
