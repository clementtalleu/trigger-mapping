<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Doctrine\ORM\Mapping\ClassMetadata;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Storage\Storage;
use Talleu\TriggerMapping\Storage\StorageResolverInterface;

final class TriggerDefinitionFactory implements TriggerDefinitionFactoryInterface
{
    public function __construct(
        private readonly StorageResolverInterface $storageResolver,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createFromAttribute(Trigger $attribute, ClassMetadata $metadata): ResolvedTrigger
    {
        if (null !== $attribute->storage && null !== Storage::tryFrom($attribute->storage)) {
            $storage = $attribute->storage;
        } elseif (null !== $attribute->storage && null == Storage::tryFrom($attribute->storage)) {
            throw new \InvalidArgumentException("{$attribute->storage} is not a valid storage, should be php or sql");
        } else {
            $storage = $this->storageResolver->getType();
        }

        return ResolvedTrigger::create(
            name: $attribute->name,
            table: $metadata->getTableName(),
            events: $attribute->on,
            timing: $attribute->timing,
            scope: $attribute->scope,
            storage: $storage,
            functionName: $attribute->function,
            onTable: $attribute->onTable,
            className: $attribute->className,
        );
    }
}
