<?php

namespace Talleu\TriggerMapping\Tests\Functional\Mysql;

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
        $triggerName = 'trg_make_test_mysql';

        $reflection = new \ReflectionClass(NoTriggerEntity::class);
        $filePath = $reflection->getFileName();
        copy($filePath, str_replace('.php', '.original', $filePath));

        $commandTester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => $triggerName,
            'on' => 'UPDATE',
            'when' => 'AFTER',
            'storage' => 'sql',
        ]);

        $commandTester->assertCommandIsSuccessful();
        $expectedSqlFile = $this->triggersDir .'/'. $triggerName . '.sql';
        $this->assertFileExists($expectedSqlFile);

        $entityContent = file_get_contents($filePath);
        $this->assertStringContainsString('#[Trigger(', $entityContent);
        $this->assertStringContainsString("name: '$triggerName'", $entityContent);
        $this->assertStringContainsString("on: ['UPDATE']", $entityContent);
    }

    public function testInvalidEventThrows(): void
    {
        $command = $this->application->find('make:trigger');
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid event/');

        $tester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => 'trg_make_invalid_event',
            'on' => 'SELECT',
            'when' => 'AFTER',
            'storage' => 'sql',
        ]);
    }

    public function testInvalidWhenThrows(): void
    {
        $command = $this->application->find('make:trigger');
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid timing/');

        $tester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => 'trg_make_invalid_when',
            'on' => 'INSERT',
            'when' => 'WHENEVER',
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
            'trigger-name' => 'trg_make_invalid_storage',
            'on' => 'INSERT',
            'when' => 'AFTER',
            'storage' => 'yaml',
        ]);
    }

    public function testMultipleEventsRejectedOnMysql(): void
    {
        $command = $this->application->find('make:trigger');
        $tester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/MySQL does not support multiple events/');

        $tester->execute([
            'entity-class' => NoTriggerEntity::class,
            'trigger-name' => 'trg_make_multi',
            'on' => 'INSERT,UPDATE',
            'when' => 'AFTER',
            'storage' => 'sql',
        ]);
    }

    public function createDirs(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->triggersDir);
    }
}
