<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Talleu\TriggerMapping\Factory\MappingCreator;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Model\ResolvedTrigger as ResolvedTriggerForClassNameTest;
use Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity\SimpleUnmappedEntity;

final class MappingCreatorTest extends TestCase
{
    public function testAddsBasicTriggerAttributeToEntity(): void
    {
        $captured = $this->runCreateMapping(
            ResolvedTrigger::create(
                name: 'trg_basic',
                table: 'simple_unmapped_entity',
                events: ['INSERT', 'UPDATE'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
            )
        );

        self::assertStringContainsString('use Talleu\\TriggerMapping\\Attribute\\Trigger;', $captured);
        self::assertStringContainsString('#[Trigger(', $captured);
        self::assertStringContainsString("name: 'trg_basic'", $captured);
        self::assertStringContainsString("on: ['INSERT', 'UPDATE']", $captured);
        self::assertStringContainsString("when: 'AFTER'", $captured);
        self::assertStringContainsString("scope: 'ROW'", $captured);
    }

    public function testAddsFunctionWhenDefined(): void
    {
        $captured = $this->runCreateMapping(
            ResolvedTrigger::create(
                name: 'trg_with_fn',
                table: 'simple_unmapped_entity',
                events: ['INSERT'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
                functionName: 'fn_audit',
            )
        );

        self::assertStringContainsString("function: 'fn_audit'", $captured);
    }

    public function testOmitsFunctionWhenNull(): void
    {
        $captured = $this->runCreateMapping(
            ResolvedTrigger::create(
                name: 'trg_no_fn',
                table: 'simple_unmapped_entity',
                events: ['INSERT'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
            )
        );

        self::assertStringNotContainsString('function:', $captured);
    }

    public function testAddsClassNameAndImportWhenClassExists(): void
    {
        $captured = $this->runCreateMapping(
            resolvedTrigger: ResolvedTrigger::create(
                name: 'trg_with_class',
                table: 'simple_unmapped_entity',
                events: ['INSERT'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
            ),
            // Use a real class in a DIFFERENT namespace from the entity, so the
            // ClassSourceManipulator must add a `use` statement.
            triggerClassFqcn: ResolvedTriggerForClassNameTest::class,
        );

        // The short name appears as `Foo::class` …
        self::assertStringContainsString('ResolvedTrigger::class', $captured);
        // … and the corresponding `use` statement is added to the file.
        self::assertStringContainsString('use Talleu\\TriggerMapping\\Model\\ResolvedTrigger;', $captured);
    }

    public function testFallsBackToStringLiteralWhenClassDoesNotExist(): void
    {
        $captured = $this->runCreateMapping(
            resolvedTrigger: ResolvedTrigger::create(
                name: 'trg_unknown_class',
                table: 'simple_unmapped_entity',
                events: ['INSERT'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
            ),
            triggerClassFqcn: 'App\\Triggers\\NotYetCreated',
        );

        // When the class does not exist yet, the FQCN is rendered as a single-quoted
        // string literal in PHP source — single backslashes only.
        self::assertStringContainsString("'App\\Triggers\\NotYetCreated'", $captured);
    }

    public function testAddsOnTableWhenProvided(): void
    {
        $captured = $this->runCreateMapping(
            resolvedTrigger: ResolvedTrigger::create(
                name: 'trg_join',
                table: 'simple_unmapped_entity',
                events: ['INSERT'],
                when: 'AFTER',
                scope: 'ROW',
                storage: 'php',
            ),
            onTable: 'user_role'
        );

        self::assertStringContainsString("onTable: 'user_role'", $captured);
    }

    /**
     * Runs createMapping against SimpleUnmappedEntity, intercepting all FS access.
     * Returns the source code that the bundle would have written to disk.
     */
    private function runCreateMapping(
        ResolvedTrigger $resolvedTrigger,
        ?string $triggerClassFqcn = null,
        ?string $onTable = null,
    ): string {
        $entityFqcn = SimpleUnmappedEntity::class;
        $entityPath = (new ClassDetails($entityFqcn))->getPath();
        $originalContent = file_get_contents($entityPath);
        self::assertNotFalse($originalContent, 'Failed to read fixture entity source');

        $captured = '';
        $fileManager = $this->createMock(FileManager::class);
        $fileManager->method('getFileContents')->with($entityPath)->willReturn($originalContent);
        $fileManager
            ->expects($this->once())
            ->method('dumpFile')
            ->willReturnCallback(function (string $path, string $content) use (&$captured, $entityPath): void {
                self::assertSame($entityPath, $path);
                $captured = $content;
            });

        (new MappingCreator($fileManager))
            ->createMapping($resolvedTrigger, $entityFqcn, $triggerClassFqcn, $onTable);

        return $captured;
    }
}
