<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional\SqlServer;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\SqlServerUpdateSchemaTestEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersSchemaUpdateTestCase;

final class TriggersSchemaUpdateTest extends AbstractTriggersSchemaUpdateTestCase
{
    public function testExecuteApplyMode(): void
    {
        $this->createSchemaForEntities([SqlServerUpdateSchemaTestEntity::class]);

        $this->assertFalse($this->triggerExists('trg_update_schema_test'), "Pre-condition failed: Trigger 'trg_update_schema_test' should not exist before running the command.");
        $command = $this->application->find('triggers:schema:update');
        $commandTester = new CommandTester($command);

        // Simulate the user typing "yes" to the confirmation prompt
        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--force' => true, '--entity' => SqlServerUpdateSchemaTestEntity::class]);
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Running in FORCE mode', $output);
        $this->assertStringContainsString("Processing trigger trg_update_schema_test", $output);
        $this->assertStringContainsString('Database schema updated successfully.', $output);
        $this->assertTrue($this->triggerExists('trg_update_schema_test'), "Post-condition failed: Trigger 'trg_update_schema_test' should exist after running the command.");
    }

    protected function createTriggerFile(string $fileName, string $content): void
    {
        // TODO: Implement createTriggerFile() method.
    }
}
