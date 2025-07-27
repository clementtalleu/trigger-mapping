<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\Factory;

use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface MappingCreatorInterface
{
    /**
     * Adds the #[Trigger] attribute to a given entity class file.
     */
    public function createMapping(
        ResolvedTrigger $resolvedTrigger,
        string          $entityFqcn,
        ?string         $triggerClassFqcn = null,
        ?string         $onTable = null
    ): void;
}
