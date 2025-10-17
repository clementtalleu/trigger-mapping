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
