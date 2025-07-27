<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Metadata;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\Mapping\Entity;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Exception\NotAnEntityException;
use Talleu\TriggerMapping\Factory\TriggerDefinitionFactory;

final readonly class TriggersMapping implements TriggersMappingInterface
{
    public function __construct(
        private DependencyFactory        $dependencyFactory,
        private TriggerDefinitionFactory $triggerDefinitionFactory,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function extractTriggerMapping(?string $entityName = null): array
    {
        if (null !== $entityName && !$this->isDoctrineEntity($entityName)) {
            throw new NotAnEntityException($entityName);
        }

        $entitiesMetadata = $this->dependencyFactory->getEntityManager()->getMetadataFactory()->getAllMetadata();

        $triggers = [];
        foreach ($entitiesMetadata as $metadatum) {
            if (null !== $entityName && $metadatum->name !== $entityName) {
                continue;
            }

            $triggersAttributes = $metadatum->getReflectionClass()->getAttributes(Trigger::class);
            if ([] === $triggersAttributes) {
                continue;
            }

            foreach ($triggersAttributes as $triggersAttribute) {
                /** @var Trigger $triggerInstance */
                $triggerInstance = $triggersAttribute->newInstance();
                $resolvedTrigger = $this->triggerDefinitionFactory->createFromAttribute($triggerInstance, $metadatum);
                $triggers[$triggerInstance->name] = $resolvedTrigger;
            }
        }

        return $triggers;
    }

    public function isDoctrineEntity(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflectionClass = new \ReflectionClass($className);
        $attributes = $reflectionClass->getAttributes(Entity::class);

        return !empty($attributes);
    }
}
