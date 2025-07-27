<?php

namespace Talleu\TriggerMapping\Tests\Functional\Postgresql;

use Symfony\Component\Console\Tester\CommandTester;
use Talleu\TriggerMapping\Tests\Application\Entity\UpdateMappingTestEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractTriggersMappingUpdateTestCase;

final class TriggersMappingUpdateTest extends AbstractTriggersMappingUpdateTestCase
{
    protected function getCreateTriggerSql(string $triggerName, string $tableName, string $timing, string $events, ?string $functionName = null): string
    {
        $functionName = $functionName ?? 'dummy_test_func';

        $functionSql = <<<SQL
            CREATE OR REPLACE FUNCTION {$functionName}()
            RETURNS trigger AS $$
            BEGIN
                -- Dummy logic
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL;

        $triggerSql = "CREATE TRIGGER {$triggerName} {$timing} {$events} ON {$tableName} FOR EACH ROW EXECUTE FUNCTION {$functionName}()";

        return $functionSql . '; ' . $triggerSql;
    }

    public function testUnmappedTriggerAddAttribute(): void
    {
        $this->createSchemaForEntities([UpdateMappingTestEntity::class]);
        $sql = $this->getCreateTriggerSql(
            'trigger_to_map',
            'update_mapping_test_entity',
            'AFTER',
            'INSERT',
            'func_to_map'
        );
        $this->executeSql($sql);

        $command = $this->application->find('triggers:mapping:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--apply' => true,
        ]);
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Mapping update process finished successfully', $commandTester->getDisplay());
        $reflection = new \ReflectionClass(UpdateMappingTestEntity::class);
        $filePath = $reflection->getFileName();
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('#[Trigger(name', $fileContent);
        $this->assertStringContainsString('name: \'trigger_to_map\'', $fileContent);
        $this->assertStringContainsString('function: \'func_to_map\'', $fileContent);
        $this->assertStringContainsString('INSERT', $fileContent);
        $this->assertStringContainsString('AFTER', $fileContent);
    }
}
