<?php

namespace Talleu\TriggerMapping\Tests\Functional\Postgresql;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Talleu\TriggerMapping\Tests\Application\Entity\NoTriggerEntity;
use Talleu\TriggerMapping\Tests\Functional\AbstractMakeTriggerTestCase;

final class MakeTriggerTest extends AbstractMakeTriggerTestCase
{
    public function testExecuteWithAllArguments(): void
    {
        $command = $this->application->find('make:trigger');
        $commandTester = new CommandTester($command);
        $triggerName = 'trg_make_test_postgresql';
        $functionName = 'func_test';

        $reflection = new \ReflectionClass(NoTriggerEntity::class);
        $filePath = $reflection->getFileName();
        copy($filePath, str_replace('.php', '.original', $filePath));

        $commandTester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => $triggerName,
            'on' => 'INSERT,UPDATE',
            'when' => 'AFTER',
            'storage' => 'sql',
            'scope' => 'ROW',
            'function-name' => $functionName,
        ]);

        $commandTester->assertCommandIsSuccessful();
        $expectedSqlFile = $this->triggersDir .'/triggers/'. $triggerName . '.sql';
        $this->assertFileExists($expectedSqlFile);

        $entityContent = file_get_contents($filePath);
        $this->assertStringContainsString('#[Trigger(', $entityContent);
        $this->assertStringContainsString("name: '$triggerName'", $entityContent);
        $this->assertStringContainsString("on: ['INSERT', 'UPDATE']", $entityContent);
        $this->assertStringContainsString("function: '$functionName'", $entityContent);
        $this->assertStringContainsString("when: 'AFTER'", $entityContent);
    }

    public function testInvalidEventThrows(): void
    {
        $command = $this->application->find('make:trigger');
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid event/');

        $tester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => 'trg_pg_invalid_event',
            'function-name' => 'fn_pg',
            'on' => 'SELECT',
            'when' => 'AFTER',
            'scope' => 'ROW',
            'storage' => 'sql',
        ]);
    }

    public function testInvalidStorageThrows(): void
    {
        $command = $this->application->find('make:trigger');
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid storage/');

        $tester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => 'trg_pg_invalid_storage',
            'function-name' => 'fn_pg',
            'on' => 'INSERT',
            'when' => 'AFTER',
            'scope' => 'ROW',
            'storage' => 'yaml',
        ]);
    }

    public function createDirs(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->triggersDir.'/triggers');
        $filesystem->mkdir($this->triggersDir.'/functions');
    }
}
