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
            'timing' => 'AFTER',
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
        $this->assertStringContainsString("on: ['UPDATE']", $entityContent);
    }

    public function createDirs(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->triggersDir.'/triggers');
        $filesystem->mkdir($this->triggersDir.'/functions');
    }
}
