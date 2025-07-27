<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Doctrine\ORM\Mapping\ClassMetadata;
use Talleu\TriggerMapping\Attribute\Trigger;
use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface TriggerDefinitionFactoryInterface
{
    /**
     * @param ClassMetadata<object> $metadata
     */
    public function createFromAttribute(Trigger $attribute, ClassMetadata $metadata): ResolvedTrigger;
}
