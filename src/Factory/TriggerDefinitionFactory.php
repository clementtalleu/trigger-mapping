<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Doctrine\ORM\Mapping\ClassMetadata;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Model\ResolvedTrigger;
use Talleu\TriggerMapping\Storage\Storage;

final readonly class TriggerDefinitionFactory implements TriggerDefinitionFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createFromAttribute(Trigger $attribute, ClassMetadata $metadata): ResolvedTrigger
    {
        if (null !== $attribute->storage && null !== Storage::tryFrom($attribute->storage)) {
            $storage = $attribute->storage;
        } elseif (null !== $attribute->storage && null === Storage::tryFrom($attribute->storage)) {
            throw new \InvalidArgumentException("{$attribute->storage} is not a valid storage, should be php or sql");
        } else {
            $storage = null;
        }

        return ResolvedTrigger::create(
            name: $attribute->name,
            table: $metadata->getTableName(),
            events: $attribute->on,
            when: $attribute->when,
            scope: $attribute->scope,
            storage: $storage,
            functionName: $attribute->function,
            onTable: $attribute->onTable,
            className: $attribute->className,
        );
    }
}
