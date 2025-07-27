<?php

namespace Talleu\TriggerMapping\Metadata;

use Talleu\TriggerMapping\Model\ResolvedTrigger;

interface TriggersMappingInterface
{
    /**
     * @return array<string, ResolvedTrigger>
     */
    public function extractTriggerMapping(?string $entityName = null): array;
}
