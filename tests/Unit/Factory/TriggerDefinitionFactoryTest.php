<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Factory;

use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Factory\TriggerDefinitionFactory;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

final class TriggerDefinitionFactoryTest extends TestCase
{
    public function testStorageFallsBackToResolverWhenAttributeStorageIsNull(): void
    {
        $factory = new TriggerDefinitionFactory($this->resolverWithType('php'));
        $resolved = $factory->createFromAttribute(
            new Trigger(name: 'trg', on: ['INSERT'], when: 'AFTER', scope: 'ROW'),
            $this->metadataWithTable('tbl'),
        );

        self::assertSame('php', $resolved->storage);
    }

    public function testAttributeStoragePhpOverridesResolver(): void
    {
        $factory = new TriggerDefinitionFactory($this->resolverWithType('sql'));
        $resolved = $factory->createFromAttribute(
            new Trigger(name: 'trg', storage: 'php'),
            $this->metadataWithTable('tbl'),
        );

        self::assertSame('php', $resolved->storage);
    }

    public function testAttributeStorageSqlOverridesResolver(): void
    {
        $factory = new TriggerDefinitionFactory($this->resolverWithType('php'));
        $resolved = $factory->createFromAttribute(
            new Trigger(name: 'trg', storage: 'sql'),
            $this->metadataWithTable('tbl'),
        );

        self::assertSame('sql', $resolved->storage);
    }

    public function testInvalidAttributeStorageThrows(): void
    {
        $factory = new TriggerDefinitionFactory($this->resolverWithType('php'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid storage/');

        $factory->createFromAttribute(
            new Trigger(name: 'trg', storage: 'yaml'),
            $this->metadataWithTable('tbl'),
        );
    }

    public function testAttributePropertiesArePropagatedToResolvedTrigger(): void
    {
        $factory = new TriggerDefinitionFactory($this->resolverWithType('php'));
        $resolved = $factory->createFromAttribute(
            new Trigger(
                name: 'trg_audit',
                function: 'fn_audit',
                on: ['INSERT', 'UPDATE'],
                when: 'BEFORE',
                scope: 'STATEMENT',
                storage: 'sql',
                className: 'App\\Triggers\\Audit',
                onTable: 'user_role',
            ),
            $this->metadataWithTable('user'),
        );

        self::assertSame('trg_audit', $resolved->name);
        self::assertSame('user', $resolved->table);
        self::assertSame(['INSERT', 'UPDATE'], $resolved->events);
        self::assertSame('BEFORE', $resolved->when);
        self::assertSame('STATEMENT', $resolved->scope);
        self::assertSame('sql', $resolved->storage);
        self::assertSame('fn_audit', $resolved->function);
        self::assertSame('App\\Triggers\\Audit', $resolved->className);
        self::assertSame('user_role', $resolved->onTable);
    }

    public function testTableComesFromMetadataNotAttribute(): void
    {
        // The Trigger attribute does not carry a `table` parameter — it is read from
        // the Doctrine ClassMetadata. This test pins that contract.
        $factory = new TriggerDefinitionFactory($this->resolverWithType('php'));
        $resolved = $factory->createFromAttribute(
            new Trigger(name: 'trg'),
            $this->metadataWithTable('the_metadata_table'),
        );

        self::assertSame('the_metadata_table', $resolved->table);
    }

    private function resolverWithType(string $type): StorageResolverInterface
    {
        $resolver = $this->createMock(StorageResolverInterface::class);
        $resolver->method('getType')->willReturn($type);

        return $resolver;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function metadataWithTable(string $tableName): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getTableName')->willReturn($tableName);

        return $metadata;
    }
}
