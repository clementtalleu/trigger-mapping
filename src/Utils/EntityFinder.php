<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;

final class EntityFinder
{
    /**
     * Lazy-built `tableName => entityFqcn` lookup map. Populated on the first
     * call and cached for the lifetime of the request, so repeat invocations
     * (typical when a command processes dozens of triggers) don't re-scan
     * every metadata of the EntityManager.
     *
     * @var array<string, string>|null
     */
    private ?array $tableToFqcn = null;

    /**
     * Lazy-built `joinTableName => entityFqcn` lookup map.
     *
     * @var array<string, string>|null
     */
    private ?array $joinTableToFqcn = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findEntityFqcnForTable(string $tableName): ?string
    {
        return $this->tableMap()[$tableName] ?? null;
    }

    public function findEntityFqcnForJoinTable(string $tableName): ?string
    {
        return $this->joinTableMap()[$tableName] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private function tableMap(): array
    {
        if (null !== $this->tableToFqcn) {
            return $this->tableToFqcn;
        }

        $map = [];
        /** @var ClassMetadata<object> $metadata */
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $map[$metadata->getTableName()] = $metadata->getName();
        }

        return $this->tableToFqcn = $map;
    }

    /**
     * @return array<string, string>
     */
    private function joinTableMap(): array
    {
        if (null !== $this->joinTableToFqcn) {
            return $this->joinTableToFqcn;
        }

        $map = [];
        /** @var ClassMetadata<object> $metadata */
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getAssociationMappings() as $assoc) {
                $joinTable = $this->extractJoinTableName($assoc);
                if (null !== $joinTable) {
                    $map[$joinTable] = $metadata->getName();
                }
            }
        }

        return $this->joinTableToFqcn = $map;
    }

    /**
     * Reads the join-table name from an association mapping in a way that works
     * for both Doctrine ORM 2 (associations are arrays) and ORM 3+ (associations
     * are `AssociationMapping` objects). Avoids the deprecated `ArrayAccess`
     * shim on ORM 3 — and will keep working on ORM 4 where that shim is removed.
     *
     * @param mixed $assoc Either an array (ORM 2) or an AssociationMapping (ORM 3+).
     */
    private function extractJoinTableName(mixed $assoc): ?string
    {
        // ORM 3+ : association mappings are typed objects.
        if (class_exists(AssociationMapping::class) && $assoc instanceof AssociationMapping) {
            // Only ManyToMany owning-side mappings expose a join table.
            // We use `property_exists` instead of an `instanceof` against
            // ManyToManyOwningSideMapping so the code keeps compiling on
            // ORM 2 where that class doesn't exist.
            if (!property_exists($assoc, 'joinTable') || null === $assoc->joinTable) {
                return null;
            }

            return $assoc->joinTable->name ?? null;
        }

        // ORM 2 : associations are plain arrays.
        if (is_array($assoc)) {
            return $assoc['joinTable']['name'] ?? null;
        }

        return null;
    }
}
