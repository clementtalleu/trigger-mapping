<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Exception\NotAnEntityException;
use Talleu\TriggerMapping\Factory\TriggerDefinitionFactory;
use Talleu\TriggerMapping\Metadata\TriggersMapping;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;
use Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity\EntityWithMultipleTriggers;
use Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity\EntityWithSingleTrigger;
use Talleu\TriggerMapping\Tests\Unit\Fixtures\Entity\SimpleUnmappedEntity;
use Talleu\TriggerMapping\Tests\Unit\Fixtures\PlainNonEntity;

final class TriggersMappingTest extends TestCase
{
    public function testExtractReturnsEmptyWhenNoEntityHasTriggers(): void
    {
        $mapping = $this->mappingWithEntities([SimpleUnmappedEntity::class => 'simple_unmapped_entity']);

        self::assertSame([], $mapping->extractTriggerMapping());
    }

    public function testExtractReturnsResolvedTriggersForSingleTriggerEntity(): void
    {
        $mapping = $this->mappingWithEntities([EntityWithSingleTrigger::class => 'entity_with_single_trigger']);

        $result = $mapping->extractTriggerMapping();

        self::assertCount(1, $result);
        self::assertArrayHasKey('trg_single', $result);
        self::assertInstanceOf(ResolvedTrigger::class, $result['trg_single']);
        self::assertSame('trg_single', $result['trg_single']->name);
        self::assertSame('entity_with_single_trigger', $result['trg_single']->table);
        self::assertSame('fn_single', $result['trg_single']->function);
    }

    public function testExtractHandlesRepeatableTriggers(): void
    {
        $mapping = $this->mappingWithEntities([EntityWithMultipleTriggers::class => 'entity_with_multiple_triggers']);

        $result = $mapping->extractTriggerMapping();

        self::assertCount(2, $result);
        self::assertArrayHasKey('trg_multi_a', $result);
        self::assertArrayHasKey('trg_multi_b', $result);
        self::assertSame('BEFORE', $result['trg_multi_a']->when);
        self::assertSame('AFTER', $result['trg_multi_b']->when);
    }

    public function testExtractFiltersByEntityName(): void
    {
        $mapping = $this->mappingWithEntities([
            EntityWithSingleTrigger::class => 'entity_with_single_trigger',
            EntityWithMultipleTriggers::class => 'entity_with_multiple_triggers',
        ]);

        $result = $mapping->extractTriggerMapping(EntityWithMultipleTriggers::class);

        self::assertCount(2, $result);
        self::assertArrayNotHasKey('trg_single', $result);
        self::assertArrayHasKey('trg_multi_a', $result);
        self::assertArrayHasKey('trg_multi_b', $result);
    }

    public function testExtractThrowsWhenEntityNameIsNotADoctrineEntity(): void
    {
        $mapping = $this->mappingWithEntities([]);

        $this->expectException(NotAnEntityException::class);
        $this->expectExceptionMessageMatches('/is not a valid doctrine entity/');

        $mapping->extractTriggerMapping(PlainNonEntity::class);
    }

    public function testExtractThrowsWhenClassDoesNotExist(): void
    {
        $mapping = $this->mappingWithEntities([]);

        $this->expectException(NotAnEntityException::class);

        $mapping->extractTriggerMapping('App\\Definitely\\Not\\A\\Class');
    }

    public function testIsDoctrineEntityForRealEntity(): void
    {
        $mapping = $this->mappingWithEntities([]);

        self::assertTrue($mapping->isDoctrineEntity(SimpleUnmappedEntity::class));
        self::assertTrue($mapping->isDoctrineEntity(EntityWithSingleTrigger::class));
    }

    public function testIsDoctrineEntityForPlainClass(): void
    {
        $mapping = $this->mappingWithEntities([]);

        self::assertFalse($mapping->isDoctrineEntity(PlainNonEntity::class));
    }

    public function testIsDoctrineEntityForNonexistentClass(): void
    {
        $mapping = $this->mappingWithEntities([]);

        self::assertFalse($mapping->isDoctrineEntity('App\\Does\\Not\\Exist'));
    }

    /**
     * @param array<class-string, string> $entitiesByFqcn FQCN => tableName
     */
    private function mappingWithEntities(array $entitiesByFqcn): TriggersMapping
    {
        $metadatas = [];
        foreach ($entitiesByFqcn as $fqcn => $tableName) {
            $metadatas[] = $this->metadataFor($fqcn, $tableName);
        }

        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($metadatas);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);

        $storageResolver = $this->createMock(StorageResolverInterface::class);
        $storageResolver->method('getType')->willReturn('php');
        $triggerDefinitionFactory = new TriggerDefinitionFactory($storageResolver);

        return new TriggersMapping($em, $triggerDefinitionFactory);
    }

    /**
     * @param class-string $fqcn
     * @return ClassMetadata<object>
     */
    private function metadataFor(string $fqcn, string $tableName): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn($fqcn);
        $metadata->method('getTableName')->willReturn($tableName);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass($fqcn));
        // Public property — has to match for the entityName filter.
        $metadata->name = $fqcn;

        return $metadata;
    }
}
