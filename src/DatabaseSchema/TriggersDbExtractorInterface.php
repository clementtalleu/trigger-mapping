<?php

declare(strict_types=1);

namespace Talleu\TriggerMapping\DatabaseSchema;

interface TriggersDbExtractorInterface
{
    /**
     * @return array<string, array{
     * name: string,
     * table: string,
     * events: string[],
     * timing: string,
     * scope: string,
     * content: string,
     * definition: ?string,
     * function: ?string
     * }>
     */
    public function listTriggers(?string $entityName = null): array;
}
