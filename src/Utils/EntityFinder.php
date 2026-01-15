<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Utils;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;

final class EntityFinder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findEntityFqcnForTable(string $tableName): ?string
    {
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        /** @var ClassMetadata<object> $metadata */
        foreach ($allMetadata as $metadata) {
            if ($metadata->getTableName() === $tableName) {
                return $metadata->getName();
            }
        }

        return null;
    }

    public function findEntityFqcnForJoinTable(string $tableName): ?string
    {
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        /** @var ClassMetadata<object> $metadata */
        foreach ($allMetadata as $metadata) {
            foreach ($metadata->getAssociationMappings() as $assoc) {
                if (isset($assoc['joinTable']['name']) && $assoc['joinTable']['name'] === $tableName) {
                    return $metadata->getName();
                }
            }
        }

        return null;
    }
}
