<?php

namespace Talleu\TriggerMapping\Tests\Functional\Mysql;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\UpdateMappingTestEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersMappingUpdateTestCase;

final class TriggersMappingUpdateTest extends AbstractTriggersMappingUpdateTestCase
{
    protected function getCreateTriggerSql(string $triggerName, string $tableName, string $when, string $events, ?string $functionName = null): string
    {
        return <<<SQL
            CREATE TRIGGER {$triggerName}
            {$when} {$events} ON {$tableName}
            FOR EACH ROW
            BEGIN
                -- Dummy logic for test trigger
                SET @dummy_var = 1;
            END;
        SQL;
    }

    public function testUnmappedTriggerAddAttribute(): void
    {
        $this->createSchemaForEntities([UpdateMappingTestEntity::class]);
        $sql = $this->getCreateTriggerSql(
            'trigger_to_map',
            'update_mapping_test_entity',
            'AFTER',
            'INSERT'
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:mapping:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--apply' => true]);
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Mapping update process finished successfully', $commandTester->getDisplay());
        $reflection = new \ReflectionClass(UpdateMappingTestEntity::class);
        $filePath = $reflection->getFileName();
        $fileContent = file_get_contents($filePath);

        $this->assertStringContainsString('#[Trigger(name:', $fileContent);
        $this->assertStringContainsString('name: \'trigger_to_map\'', $fileContent);
        $this->assertStringContainsString('on: [\'INSERT\']', $fileContent);
        $this->assertStringContainsString('when: \'AFTER\'', $fileContent);
        // We assert that the function parameter is NOT present for MySQL
        $this->assertStringNotContainsString('function:', $fileContent);
    }
}
