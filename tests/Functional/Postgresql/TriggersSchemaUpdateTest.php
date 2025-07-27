<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Functional\Postgresql;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\UpdateSchemaTestEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersSchemaUpdateTestCase;

final class TriggersSchemaUpdateTest extends AbstractTriggersSchemaUpdateTestCase
{
    public function testExecuteApplyMode(): void
    {
        $this->createSchemaForEntities([UpdateSchemaTestEntity::class]);
        $this->assertFalse($this->triggerExists('trg_update_schema_test'), "Pre-condition failed: Trigger 'trg_update_schema_test' should not exist before running the command.");

        $this->assertFalse($this->triggerExists('trg_update_schema_test'), "Pre-condition failed: Trigger 'trg_update_schema_test' should not exist before running the command.");
        $command = $this->application->find('triggers:schema:update');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['yes']);
        $commandTester->execute(['--force' => true, '--entity' => UpdateSchemaTestEntity::class]);
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Processing trigger trg_update_schema_test', $output);
        $this->assertStringContainsString('Database schema updated successfully', $output);
    }
}
