<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Tests\Unit\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;
use Talleu\TriggerMapping\Utils\EntityFinder;

final class EntityFinderTest extends TestCase
{
    public function testFindEntityFqcnForTableHit(): void
    {
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\User', 'user', []),
            $this->metadata('App\\Order', 'orders', []),
        ]);

        self::assertSame('App\\Order', $finder->findEntityFqcnForTable('orders'));
    }

    public function testFindEntityFqcnForTableMiss(): void
    {
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\User', 'user', []),
        ]);

        self::assertNull($finder->findEntityFqcnForTable('does_not_exist'));
    }

    public function testFindEntityFqcnForTableEmptyMetadata(): void
    {
        $finder = $this->finderWithMetadata([]);

        self::assertNull($finder->findEntityFqcnForTable('any_table'));
    }

    public function testFindEntityFqcnForJoinTableHit(): void
    {
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\User', 'user', [
                'roles' => ['joinTable' => ['name' => 'user_role']],
            ]),
            $this->metadata('App\\Role', 'role', []),
        ]);

        self::assertSame('App\\User', $finder->findEntityFqcnForJoinTable('user_role'));
    }

    public function testFindEntityFqcnForJoinTableMiss(): void
    {
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\User', 'user', [
                'roles' => ['joinTable' => ['name' => 'user_role']],
            ]),
        ]);

        self::assertNull($finder->findEntityFqcnForJoinTable('not_a_join_table'));
    }

    public function testFindEntityFqcnForJoinTableIgnoresAssociationsWithoutJoinTable(): void
    {
        // ManyToOne / OneToMany associations don't have a `joinTable` key.
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\Order', 'orders', [
                'customer' => ['targetEntity' => 'App\\Customer'],
            ]),
        ]);

        self::assertNull($finder->findEntityFqcnForJoinTable('customer'));
    }

    public function testFindEntityFqcnForJoinTableDoesNotMatchPrimaryTable(): void
    {
        $finder = $this->finderWithMetadata([
            $this->metadata('App\\User', 'user', []),
        ]);

        self::assertNull(
            $finder->findEntityFqcnForJoinTable('user'),
            'Primary table names must not be returned by findEntityFqcnForJoinTable'
        );
    }

    /**
     * @param ClassMetadata<object>[] $metadata
     */
    private function finderWithMetadata(array $metadata): EntityFinder
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($metadata);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);

        return new EntityFinder($em);
    }

    /**
     * @param array<string, mixed> $associationMappings
     * @return ClassMetadata<object>
     */
    private function metadata(string $fqcn, string $tableName, array $associationMappings): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn($fqcn);
        $metadata->method('getTableName')->willReturn($tableName);
        $metadata->method('getAssociationMappings')->willReturn($associationMappings);

        return $metadata;
    }
}
